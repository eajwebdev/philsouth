<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Item;
use App\Models\Site;
use App\Models\WithdrawalSlip;
use App\Notifications\WorkflowNotification;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalSlipController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WithdrawalSlip::class);

        $slips = WithdrawalSlip::query()
            ->forUser($request->user())
            ->with(['site:id,code,name', 'preparedBy:id,name'])
            ->withCount('items')
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w->where('ws_no', 'like', "%{$s}%")->orWhere('delivered_to', 'like', "%{$s}%"));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('withdrawals/index', [
            'slips' => $slips,
            'filters' => [
                'search' => $request->string('search')->value(),
                'status' => $request->string('status')->value(),
            ],
            'can' => ['create' => $request->user()->can('create', WithdrawalSlip::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WithdrawalSlip::class);

        $sites = $request->user()->accessibleSites();

        return Inertia::render('withdrawals/create', [
            'sites' => $sites->map->only('id', 'code', 'name'),
            'items' => $this->itemOptions(),
            // Roster names per site, so "Delivered to" can be an actual person.
            'employees' => Employee::query()
                ->active()
                ->whereIn('site_id', $sites->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'site_id', 'name', 'position'])
                ->groupBy('site_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WithdrawalSlip::class);

        $data = $this->validateSlip($request);
        $site = Site::findOrFail($data['site_id']);
        abort_unless($request->user()->canAccessSite($site), 403);

        $ws = DB::transaction(function () use ($data, $request) {
            $ws = WithdrawalSlip::create([
                // Manual entry: the number comes from the pre-printed booklet slip.
                'ws_no' => trim($data['ws_no']),
                'project_code' => $data['project_code'] ?? null,
                'site_id' => $data['site_id'],
                'date' => $data['date'],
                'time' => $data['time'] ?? null,
                'requested_by_type' => $data['requested_by_type'],
                'requested_by_other' => $data['requested_by_other'] ?? null,
                'delivered_to' => $data['delivered_to'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => 'draft',
                'prepared_by' => $request->user()->id,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $ws->items()->create($line);
            }

            return $ws;
        });

        return redirect()->route('withdrawals.show', $ws)->with('success', "Draft {$ws->ws_no} created.");
    }

    public function show(Request $request, WithdrawalSlip $withdrawal): Response
    {
        $this->authorize('view', $withdrawal);

        $withdrawal->load([
            'site:id,code,name',
            'items.variant:id,item_id,sku,label,uom',
            'items.variant.item:id,code,description,uom',
            'preparedBy:id,name',
            'approvedBy:id,name',
            'releasedBy:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('withdrawals/show', [
            'slip' => $withdrawal,
            'can' => [
                'submit' => $user->can('submit', $withdrawal),
                'approve' => $user->can('approve', $withdrawal),
                'reject' => $user->can('reject', $withdrawal),
                'release' => $user->can('release', $withdrawal),
                'receive' => $user->can('receive', $withdrawal),
                'cancel' => $user->can('cancel', $withdrawal),
            ],
        ]);
    }

    /**
     * Stream the withdrawal slip as a PDF laid out like the F-INV-001 paper form.
     */
    public function pdf(Request $request, WithdrawalSlip $withdrawal)
    {
        $this->authorize('view', $withdrawal);

        $withdrawal->load([
            'site:id,code,name',
            'items.variant:id,item_id,sku,label,uom',
            'items.variant.item:id,code,description,uom',
            'preparedBy:id,name',
            'approvedBy:id,name',
            'releasedBy:id,name',
        ]);

        return Pdf::loadView('pdf.withdrawal-slip', ['slip' => $withdrawal])
            ->setPaper('a4', 'portrait')
            ->stream("withdrawal-{$withdrawal->ws_no}.pdf");
    }

    public function submit(WithdrawalSlip $withdrawal): RedirectResponse
    {
        $this->authorize('submit', $withdrawal);

        $withdrawal->update(['status' => 'pending_approval']);
        $withdrawal->loadMissing('site');

        AuditLog::record('withdrawal.submitted', $withdrawal, "{$withdrawal->ws_no} submitted for approval");
        Notification::send(
            $withdrawal->site->engineers()->get(),
            new WorkflowNotification(
                'Withdrawal awaiting approval',
                "{$withdrawal->ws_no} at {$withdrawal->site->name} needs your approval.",
                route('withdrawals.show', $withdrawal->id),
                'clipboard-list',
            ),
        );

        return back()->with('success', "{$withdrawal->ws_no} submitted for approval.");
    }

    public function approve(WithdrawalSlip $withdrawal, Request $request): RedirectResponse
    {
        $this->authorize('approve', $withdrawal);

        $withdrawal->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        AuditLog::record('withdrawal.approved', $withdrawal, "{$withdrawal->ws_no} approved");
        $withdrawal->preparedBy?->notify(new WorkflowNotification(
            'Withdrawal approved',
            "{$withdrawal->ws_no} was approved — ready to release.",
            route('withdrawals.show', $withdrawal->id),
            'check',
        ));

        return back()->with('success', "{$withdrawal->ws_no} approved.");
    }

    public function reject(WithdrawalSlip $withdrawal, Request $request): RedirectResponse
    {
        $this->authorize('reject', $withdrawal);

        $data = $request->validate(['reject_reason' => ['nullable', 'string', 'max:500']]);

        $withdrawal->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'reject_reason' => $data['reject_reason'] ?? null,
        ]);

        AuditLog::record('withdrawal.rejected', $withdrawal, "{$withdrawal->ws_no} rejected", [
            'reason' => $data['reject_reason'] ?? null,
        ]);
        $withdrawal->preparedBy?->notify(new WorkflowNotification(
            'Withdrawal rejected',
            "{$withdrawal->ws_no} was rejected".($data['reject_reason'] ? ": {$data['reject_reason']}" : '.'),
            route('withdrawals.show', $withdrawal->id),
            'x',
        ));

        return back()->with('success', "{$withdrawal->ws_no} rejected.");
    }

    /**
     * Release the slip: posts OUT usage movements. NO RELEASE WITHOUT APPROVAL
     * is enforced by the policy (only an approved slip may be released).
     */
    public function release(WithdrawalSlip $withdrawal, Request $request, StockService $stock): RedirectResponse
    {
        $this->authorize('release', $withdrawal);

        $withdrawal->load('items.variant', 'site');

        try {
            DB::transaction(function () use ($withdrawal, $request, $stock) {
                foreach ($withdrawal->items as $line) {
                    $stock->postMovement(
                        $withdrawal->site,
                        $line->variant,
                        'out',
                        'usage',
                        (float) $line->qty,
                        [
                            'reference' => $withdrawal,
                            'dr_ws_no' => $withdrawal->ws_no,
                            'issued_to' => $withdrawal->delivered_to,
                            'movement_date' => now()->toDateString(),
                            'created_by' => $request->user()->id,
                        ],
                    );
                }

                $withdrawal->update([
                    'status' => 'released',
                    'released_by' => $request->user()->id,
                    'released_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record('withdrawal.released', $withdrawal, "{$withdrawal->ws_no} released — stock issued");

        return back()->with('success', "{$withdrawal->ws_no} released — stock issued.");
    }

    public function receive(WithdrawalSlip $withdrawal, Request $request): RedirectResponse
    {
        $this->authorize('receive', $withdrawal);

        $withdrawal->update([
            'status' => 'received',
            'received_by' => $withdrawal->delivered_to ?: $request->user()->name,
            'received_at' => now(),
        ]);

        return back()->with('success', "{$withdrawal->ws_no} marked received.");
    }

    public function cancel(WithdrawalSlip $withdrawal): RedirectResponse
    {
        $this->authorize('cancel', $withdrawal);

        $withdrawal->update(['status' => 'cancelled']);

        return back()->with('success', "{$withdrawal->ws_no} cancelled.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSlip(Request $request): array
    {
        return $request->validate([
            'ws_no' => ['required', 'string', 'max:50', Rule::unique('withdrawal_slips', 'ws_no')],
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'project_code' => ['nullable', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'string', 'max:20'],
            'requested_by_type' => ['required', Rule::in(['subcon', 'group_a', 'group_b', 'others'])],
            'requested_by_other' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('requested_by_type') === 'others')],
            'delivered_to' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_variant_id' => ['required', 'integer', Rule::exists('item_variants', 'id')],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
    }

    protected function itemOptions(): Collection
    {
        return Item::query()
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)->orderByDesc('is_default')->orderBy('sku')])
            ->orderBy('code')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'code' => $item->code,
                'description' => $item->description,
                'uom' => $item->uom,
                'has_variants' => $item->has_variants,
                'variants' => $item->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'label' => $v->label,
                    'uom' => $v->uom ?: $item->uom,
                    'barcode' => $v->barcode,
                    'is_default' => $v->is_default,
                ])->values(),
            ]);
    }
}
