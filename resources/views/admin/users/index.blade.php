@extends('layouts.app', ['title' => 'Equipo y permisos · SIID Meta'])

@section('content')
<div class="space-y-6">
    <header>
        <p class="eyebrow">Administración</p>
        <h1 class="page-title">Equipo y permisos</h1>
        <p class="page-subtitle">Cree cuentas y defina con claridad qué puede hacer cada persona.</p>
    </header>

    <details class="panel action-disclosure" @if($errors->any()) open @endif>
        <summary><div><h2>Agregar una persona</h2><span>La cuenta quedará activa inmediatamente.</span></div><strong>Abrir formulario</strong></summary>
        <form method="post" action="{{ route('admin.users.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@csrf
            <label class="field"><span>Nombre completo</span><input name="name" value="{{ old('name') }}" required></label>
            <label class="field"><span>Correo institucional</span><input type="email" name="email" value="{{ old('email') }}" required></label>
            <label class="field"><span>Contraseña inicial</span><input type="password" name="password" minlength="12" required><small>Mínimo 12 caracteres.</small></label>
            <label class="field"><span>Rol</span><select name="role">@foreach(\App\Enums\UserRole::cases() as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
            <button class="btn-primary sm:col-span-2 xl:col-span-4">Crear cuenta</button>
        </form>
    </details>

    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-xl font-semibold">Personas con acceso</h2><p class="mt-1 text-sm text-slate-500">{{ $users->count() }} cuentas registradas.</p></div><p class="text-xs text-slate-500">Gerencia, Apoyo y Administración pueden aprobar publicaciones.</p></div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="divide-y divide-slate-100">
                @foreach($users as $user)
                    <form method="post" action="{{ route('admin.users.update', $user) }}" class="grid gap-4 p-5 md:grid-cols-[minmax(180px,1fr)_minmax(210px,280px)_130px_auto] md:items-center">@csrf @method('PATCH')
                        <div><strong class="block">{{ $user->name }}</strong><p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p>@if($user->is(auth()->user()))<span class="mt-2 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Tu cuenta</span>@endif</div>
                        @if($user->is(auth()->user()))<input type="hidden" name="role" value="{{ $user->role->value }}"><input type="hidden" name="active" value="1">@endif
                        <label class="field"><span class="md:sr-only">Rol</span><select name="role" aria-label="Rol de {{ $user->name }}" @disabled($user->is(auth()->user()))>@foreach(\App\Enums\UserRole::cases() as $role)<option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>@endforeach</select></label>
                        <label class="field"><span class="md:sr-only">Estado</span><select name="active" aria-label="Estado de {{ $user->name }}" @disabled($user->is(auth()->user()))><option value="1" @selected($user->active)>Activo</option><option value="0" @selected(!$user->active)>Inactivo</option></select></label>
                        <button class="btn-secondary" @disabled($user->is(auth()->user()))>{{ $user->is(auth()->user()) ? 'Protegida' : 'Guardar' }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
