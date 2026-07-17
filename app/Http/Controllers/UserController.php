<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    protected array $assignableRoles = ['administrator', 'engineer', 'ics'];

    public function index(Request $request): RedirectResponse|Response
    {
        $this->ensureCanManage($request);

        $users = User::query()
            ->with('roles:id,name', 'sites:id,code,name')
            // Superadmin accounts are invisible to everyone below superadmin.
            ->when(! $request->user()->hasRole('superadmin'), function ($q) {
                $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin'));
            })
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search')->value();
                $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => ['search' => $request->string('search')->value()],
            'roles' => $this->availableRoles($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($this->availableRoles($request))],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureCanTouch($request, $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in($this->availableRoles($request))],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            ...(($data['password'] ?? null) ? ['password' => Hash::make($data['password'])] : []),
        ]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureCanTouch($request, $user);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    protected function ensureCanManage(Request $request): void
    {
        abort_unless($request->user()->can('users.manage'), 403);
    }

    /** Only a superadmin may edit or delete a superadmin account. */
    protected function ensureCanTouch(Request $request, User $user): void
    {
        abort_if($user->hasRole('superadmin') && ! $request->user()->hasRole('superadmin'), 403);
    }

    /**
     * Superadmin may also create superadmins; administrator cannot.
     *
     * @return array<int, string>
     */
    protected function availableRoles(Request $request): array
    {
        return $request->user()->hasRole('superadmin')
            ? ['superadmin', ...$this->assignableRoles]
            : $this->assignableRoles;
    }
}
