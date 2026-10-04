<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('role')->latest()->get(),
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('label')->get(),
        ]);
    }

    public function roles(): RedirectResponse
    {
        return redirect()->to(route('users.index').'#configured-roles');
    }

    public function permissions(): View
    {
        return view('users.access', [
            'title' => 'Permissions',
            'records' => Permission::query()->with('roles')->orderBy('label')->get(),
        ]);
    }

    public function passwordResets(): View
    {
        return view('users.password-resets', [
            'users' => User::query()->with('role')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::query()->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            ...$validated,
            'username' => str($validated['username'])->lower()->toString(),
            'is_active' => true,
        ]);

        Audit::record($request, 'users', 'created', User::class, $user->id, null, $user->only(['name', 'username', 'email', 'role_id', 'is_active']));

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'roles' => Role::query()->orderBy('label')->get(),
            'managedUser' => $user->load('role'),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $oldValues = $user->only(['name', 'username', 'email', 'role_id', 'is_active']);
        $payload = [
            ...$validated,
            'username' => str($validated['username'])->lower()->toString(),
        ];

        if (blank($payload['password'] ?? null)) {
            unset($payload['password']);
        }

        $user->update($payload);

        Audit::record($request, 'users', 'updated', User::class, $user->id, $oldValues, $user->fresh()->only(['name', 'username', 'email', 'role_id', 'is_active']));

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot deactivate your own account.');

        $oldValues = $user->only(['is_active']);
        $user->update(['is_active' => false]);

        Audit::record($request, 'users', 'deactivated', User::class, $user->id, $oldValues, $user->only(['is_active']));

        return back()->with('success', 'User deactivated successfully.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $oldValues = $user->only(['is_active']);
        $user->update(['is_active' => true]);

        Audit::record($request, 'users', 'reactivated', User::class, $user->id, $oldValues, $user->only(['is_active']));

        return back()->with('success', 'User reactivated successfully.');
    }

    public function password(): View
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        Audit::record($request, 'auth', 'password_changed', User::class, $request->user()->id);

        return back()->with('success', 'Password updated successfully.');
    }
}
