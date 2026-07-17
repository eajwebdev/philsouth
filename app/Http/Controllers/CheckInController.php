<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Models\LocationStamp;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $checkIns = CheckIn::query()
            ->forUser($user)
            ->with(['user:id,name', 'site:id,code,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (CheckIn $c) => [
                'id' => $c->id,
                'user' => $c->user?->name,
                'site' => $c->site?->code,
                'site_name' => $c->site?->name,
                'latitude' => $c->latitude !== null ? (float) $c->latitude : null,
                'longitude' => $c->longitude !== null ? (float) $c->longitude : null,
                'accuracy_m' => $c->accuracy_m !== null ? (float) $c->accuracy_m : null,
                'unavailable_reason' => $c->unavailable_reason,
                'note' => $c->note,
                'at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('check-in/index', [
            'checkIns' => $checkIns,
            'sites' => $user->accessibleSites()->map->only('id', 'code', 'name'),
        ]);
    }

    /** Record an arrival at a site. Location is best-effort — never blocking. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', Rule::exists('sites', 'id')],
            'note' => ['nullable', 'string', 'max:255'],
            ...LocationStamp::rules(),
        ]);

        $site = Site::findOrFail($data['site_id']);
        abort_unless($request->user()->canAccessSite($site), 403);

        CheckIn::create([
            'site_id' => $site->id,
            'user_id' => $request->user()->id,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_m' => $data['accuracy_m'] ?? null,
            'unavailable_reason' => $data['unavailable_reason'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', "Checked in at {$site->name}.");
    }
}
