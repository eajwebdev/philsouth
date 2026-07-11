<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteTeamController extends Controller
{
    /**
     * Engineer view: manage ICS assignments for one of their own sites.
     */
    public function edit(Request $request, Site $site): Response
    {
        $this->authorize('view', $site);

        $user = $request->user();

        $employees = $site->employees()
            ->with('user:id,email')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'position' => $e->position,
                'is_active' => $e->is_active,
                'email' => $e->user?->email,
                'has_access' => $e->user_id !== null,
                'pages' => $e->user ? $e->user->getDirectPermissions()->pluck('name')->values() : [],
            ]);

        return Inertia::render('sites/team', [
            'site' => $site->only('id', 'code', 'name', 'address', 'is_active'),
            'engineers' => $site->engineers()->get(['users.id', 'name', 'email']),
            'assignedIcs' => $site->icsUsers()->get(['users.id', 'name', 'email'])->pluck('id'),
            'icsUsers' => User::role('ics')->orderBy('name')->get(['id', 'name', 'email']),
            'employees' => $employees,
            // Destinations for moving a roster member to another site.
            'allSites' => Site::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'pageCatalog' => config('access.pages'),
            'can' => [
                'assignIcs' => $user->can('assignIcs', $site),
                'manageTeam' => $user->can('manageTeam', $site),
                'grantAccess' => $user->can('grantAccess', $site),
            ],
        ]);
    }

    /**
     * Engineer may only assign ICS to sites they are assigned to.
     * Enforced by SitePolicy::assignIcs (not just the UI).
     */
    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('assignIcs', $site);

        $validated = $request->validate([
            'ics_ids' => ['array'],
            'ics_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $icsIds = User::role('ics')
            ->whereIn('id', $validated['ics_ids'] ?? [])
            ->pluck('id');

        $currentIcs = $site->icsUsers()->pluck('users.id');

        $toDetach = $currentIcs->diff($icsIds);
        if ($toDetach->isNotEmpty()) {
            $site->users()->detach($toDetach->all());
        }

        $attach = [];
        foreach ($icsIds as $id) {
            $attach[$id] = ['assigned_by' => $request->user()->id];
        }
        if (! empty($attach)) {
            $site->users()->syncWithoutDetaching($attach);
        }

        return back()->with('success', 'ICS assignments updated.');
    }
}
