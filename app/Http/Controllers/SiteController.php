<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Site::class);

        $user = $request->user();

        $sites = Site::query()
            ->when(! $user->bypassesSiteScope(), fn ($q) => $q->whereIn('id', $user->siteIds()))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
            })
            ->withCount([
                'users as engineers_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'engineer')),
                'users as ics_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'ics')),
            ])
            ->with(['users' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['engineer', 'ics']))->with('roles:id,name')])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('sites/index', [
            'sites' => $sites,
            'filters' => ['search' => $request->string('search')->value()],
            'engineers' => User::role('engineer')->orderBy('name')->get(['id', 'name', 'email']),
            'can' => [
                'manage' => $user->can('create', Site::class),
                'assignEngineer' => $user->hasPermissionTo('assign.engineer') || $user->bypassesSiteScope(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Site::class);

        Site::create($this->validateSite($request));

        return back()->with('success', 'Site created.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);

        $site->update($this->validateSite($request, $site));

        return back()->with('success', 'Site updated.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);

        $site->delete();

        return back()->with('success', 'Site deleted.');
    }

    /**
     * Administrator attaches/detaches engineers for a site.
     */
    public function syncEngineers(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('assignEngineer', $site);

        $validated = $request->validate([
            'engineer_ids' => ['array'],
            'engineer_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $engineerIds = User::role('engineer')
            ->whereIn('id', $validated['engineer_ids'] ?? [])
            ->pluck('id')
            ->all();

        $this->syncRoleMembers($site, 'engineer', $engineerIds, $request->user()->id);

        return back()->with('success', 'Engineer assignments updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSite(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('sites', 'code')->ignore($site?->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Sync the members of a site that hold a given role, leaving pivot rows
     * for other roles untouched.
     *
     * @param  array<int, int>  $userIds
     */
    protected function syncRoleMembers(Site $site, string $role, array $userIds, int $assignedBy): void
    {
        $currentOfRole = $site->users()
            ->whereHas('roles', fn ($r) => $r->where('name', $role))
            ->pluck('users.id');

        $toDetach = $currentOfRole->diff($userIds);
        if ($toDetach->isNotEmpty()) {
            $site->users()->detach($toDetach->all());
        }

        $attach = [];
        foreach ($userIds as $id) {
            $attach[$id] = ['assigned_by' => $assignedBy];
        }
        if (! empty($attach)) {
            $site->users()->syncWithoutDetaching($attach);
        }
    }
}
