<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeController extends Controller
{
    /** Add a roster member (name + position) to a site. */
    public function store(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('manageTeam', $site);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
        ]);

        $site->employees()->create([
            'name' => $data['name'],
            'position' => $data['position'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Employee added.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manageTeam', $employee->site);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $employee->update($data);

        // Keep the linked login's display name in step with the roster.
        if ($employee->user) {
            $employee->user->update(['name' => $data['name']]);
        }

        return back()->with('success', 'Employee updated.');
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manageTeam', $employee->site);

        // Removing a roster member also removes any login they were granted.
        if ($employee->user) {
            $this->authorize('grantAccess', $employee->site);
            $this->deleteLogin($employee->user);
        }

        $employee->delete();

        return back()->with('success', 'Employee removed.');
    }

    /**
     * Transfer a roster member to another site. If they have a login, their site
     * assignment follows so their access scope moves with them.
     */
    public function transfer(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manageTeam', $employee->site);

        $data = $request->validate([
            'to_site_id' => ['required', 'integer', 'different:'.$employee->site_id, Rule::exists('sites', 'id')],
        ]);

        $target = Site::findOrFail($data['to_site_id']);
        $from = $employee->site;

        DB::transaction(function () use ($employee, $target, $from, $request) {
            // Move any login's site assignment along with the person.
            if ($employee->user) {
                $employee->user->sites()->detach($from->id);
                $employee->user->sites()->syncWithoutDetaching([
                    $target->id => ['assigned_by' => $request->user()->id],
                ]);
            }

            $employee->update(['site_id' => $target->id, 'is_active' => true]);
        });

        \App\Models\AuditLog::record('employee.transferred', $employee->fresh(),
            "{$employee->name} moved from {$from->code} to {$target->code}", [
                'from' => $from->code,
                'to' => $target->code,
            ], $target->id);

        return back()->with('success', "{$employee->name} moved to {$target->name}.");
    }

    /** Grant this roster member a system login with a chosen set of pages. */
    public function grantAccess(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('grantAccess', $employee->site);

        abort_if($employee->user_id !== null, 400, 'This employee already has access.');

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'pages' => ['array'],
            'pages.*' => ['string', Rule::in($this->pageKeys())],
        ]);

        DB::transaction(function () use ($employee, $data, $request) {
            $user = User::create([
                'name' => $employee->name,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->syncRoles(['staff']);
            $user->syncPermissions($data['pages'] ?? []);
            $user->sites()->syncWithoutDetaching([$employee->site_id => ['assigned_by' => $request->user()->id]]);

            $employee->update(['user_id' => $user->id]);
        });

        \App\Models\AuditLog::record('access.granted', $employee, "Login granted to {$employee->name}", [
            'email' => $data['email'],
            'pages' => $data['pages'] ?? [],
        ], $employee->site_id);

        return back()->with('success', "Access granted to {$employee->name}.");
    }

    /** Update the pages (and optionally password) of an employee's login. */
    public function updateAccess(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('grantAccess', $employee->site);

        abort_if($employee->user_id === null, 400, 'This employee has no login yet.');

        $data = $request->validate([
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'pages' => ['array'],
            'pages.*' => ['string', Rule::in($this->pageKeys())],
        ]);

        $user = $employee->user;
        $user->syncPermissions($data['pages'] ?? []);
        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return back()->with('success', "Access updated for {$employee->name}.");
    }

    /** Revoke the login but keep the roster member. */
    public function revokeAccess(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('grantAccess', $employee->site);

        if ($employee->user) {
            $this->deleteLogin($employee->user);
        }
        $employee->update(['user_id' => null]);

        \App\Models\AuditLog::record('access.revoked', $employee, "Login revoked for {$employee->name}", [], $employee->site_id);

        return back()->with('success', "Access revoked for {$employee->name}.");
    }

    protected function deleteLogin(User $user): void
    {
        $user->syncRoles([]);
        $user->syncPermissions([]);
        $user->sites()->detach();
        $user->delete();
    }

    /**
     * @return array<int, string>
     */
    protected function pageKeys(): array
    {
        return array_column(config('access.pages', []), 'key');
    }
}
