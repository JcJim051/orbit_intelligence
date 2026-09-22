<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        abort_unless(request()->user()?->isAdmin() || request()->user()?->role === UserRole::Manager, 403);

        return view('admin.users.index', ['users' => User::query()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);
        User::create([...$data, 'password' => Hash::make($data['password']), 'active' => true]);

        return back()->with('status', 'Usuario creado.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->role === UserRole::Manager, 403);
        $data = $request->validate(['role' => ['required', Rule::enum(UserRole::class)], 'active' => ['required', 'boolean']]);
        $requestedRole = UserRole::from($data['role']);
        if (! $request->user()->isAdmin()) {
            abort_unless(
                in_array($user->role, [UserRole::Member, UserRole::SiidManager], true)
                && in_array($requestedRole, [UserRole::Member, UserRole::SiidManager], true),
                403,
                'Gerencia solo puede autorizar o retirar el rol Gestor SIID.',
            );
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
}
