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

        return Inertia::render('sites/team', [
            'site' => $site->only('id', 'code', 'name', 'address', 'is_active'),
            'engineers' => $site->engineers()->get(['users.id', 'name', 'email']),
            'assignedIcs' => $site->icsUsers()->get(['users.id', 'name', 'email'])->pluck('id'),
            'icsUsers' => User::role('ics')->orderBy('name')->get(['id', 'name', 'email']),
            'can' => [
                'assignIcs' => $user->can('assignIcs', $site),
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
