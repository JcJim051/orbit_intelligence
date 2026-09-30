<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canAccessPlatformAdministration(), 403);

        $users = User::query()
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request): void {
                $search = '%'.$request->string('q')->trim().'%';
                $query->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            }))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->when($request->filled('status'), fn ($query) => $query->where('active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'totals' => [
                'users' => User::query()->count(),
                'active' => User::query()->where('active', true)->count(),
                'inactive' => User::query()->where('active', false)->count(),
                'admins' => User::query()->where('role', UserRole::Admin)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['nullable', 'boolean'],
        ]);

        User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'active' => $request->boolean('active', true),
        ]);

        return redirect(Workspace::getUrl(['workspace' => 'usuarios']))
            ->with('status', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->canAccessPlatformAdministration(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'active' => ['required', 'boolean'],
        ]);
        $requestedRole = UserRole::from($data['role']);

        if (! $request->user()->isAdmin()) {
            abort_unless(
                in_array($user->role, [UserRole::Member, UserRole::SiidManager], true)
                && in_array($requestedRole, [UserRole::Member, UserRole::SiidManager], true),
                403,
                'Gerencia solo puede autorizar o retirar el rol Gestor SIID.',
            );

            $data['name'] = $user->name;
            $data['email'] = $user->email;
        }

        abort_if($user->is($request->user()) && ! $request->boolean('active'), 422, 'No puedes desactivar tu propia cuenta.');
        abort_if(
            $user->is($request->user()) && $requestedRole !== UserRole::Admin,
            422,
            'No puedes quitarte el rol administrador desde tu propia cuenta. Otro administrador debe realizar ese cambio.',
        );

        $user->update($data);
        if (! $user->active) {
            $user->tokens()->delete();
        }

        return back()->with('status', 'Usuario actualizado.');
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_if($user->is($request->user()), 422, 'Use su perfil personal para cambiar su propia contraseña.');

        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        $user->tokens()->delete();

        return back()->with('status', 'Contraseña restablecida y tokens revocados.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_if($user->is($request->user()), 422, 'No puedes retirar tu propia cuenta.');

        $user->update(['active' => false]);
        $user->tokens()->delete();

        return back()->with('status', 'Acceso retirado. La cuenta quedó inactiva para conservar trazabilidad.');
    }
}
