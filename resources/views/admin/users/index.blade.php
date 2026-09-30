@extends('layouts.app', ['title' => 'Usuarios, roles y permisos · SIID 2.0'])

@section('content')
@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser->isAdmin();
    $assignableRoles = $isAdmin ? $roles : [\App\Enums\UserRole::Member, \App\Enums\UserRole::SiidManager];
@endphp

<div class="space-y-7">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="eyebrow">Administración de plataforma</p>
            <h1 class="page-title">Usuarios, roles y permisos</h1>
            <p class="page-subtitle">Cree cuentas, restablezca accesos y controle qué puede hacer cada rol dentro de SIID 2.0.</p>
        </div>
        <a class="btn-secondary" href="{{ \App\Filament\Pages\PlatformAdministration::getUrl() }}">← Administración</a>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article class="panel">
            <p class="eyebrow">Total</p>
            <strong class="mt-2 block text-4xl font-black">{{ $totals['users'] }}</strong>
            <span class="text-sm text-slate-500">cuentas registradas</span>
        </article>
        <article class="panel">
            <p class="eyebrow">Activas</p>
            <strong class="mt-2 block text-4xl font-black text-emerald-700">{{ $totals['active'] }}</strong>
            <span class="text-sm text-slate-500">pueden iniciar sesión</span>
        </article>
        <article class="panel">
            <p class="eyebrow">Inactivas</p>
            <strong class="mt-2 block text-4xl font-black text-slate-500">{{ $totals['inactive'] }}</strong>
            <span class="text-sm text-slate-500">conservadas por trazabilidad</span>
        </article>
        <article class="panel">
            <p class="eyebrow">Administradores</p>
            <strong class="mt-2 block text-4xl font-black text-indigo-700">{{ $totals['admins'] }}</strong>
            <span class="text-sm text-slate-500">con control técnico</span>
        </article>
    </section>

    @if($isAdmin)
        <details class="panel action-disclosure" @if($errors->any()) open @endif>
            <summary>
                <div>
                    <h2>Crear usuario</h2>
                    <span>La cuenta puede quedar activa inmediatamente o preparada para activarse después.</span>
                </div>
                <strong>Abrir formulario</strong>
            </summary>

            <form method="post" action="{{ route('admin.users.store') }}" class="mt-5 grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                @csrf
                <label class="field">
                    <span>Nombre completo</span>
                    <input name="name" value="{{ old('name') }}" required autocomplete="name">
                </label>
                <label class="field">
                    <span>Correo institucional</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                </label>
                <label class="field">
                    <span>Contraseña inicial</span>
                    <input type="password" name="password" minlength="12" required autocomplete="new-password">
                    <small>Mínimo 12 caracteres.</small>
                </label>
                <label class="field">
                    <span>Confirmar contraseña</span>
                    <input type="password" name="password_confirmation" minlength="12" required autocomplete="new-password">
                </label>
                <label class="field">
                    <span>Rol</span>
                    <select name="role" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Estado</span>
                    <select name="active">
                        <option value="1" @selected(old('active', '1') === '1')>Activo</option>
                        <option value="0" @selected(old('active') === '0')>Inactivo</option>
                    </select>
                </label>
                <div class="xl:col-span-4">
                    <button class="btn-primary" type="submit">Crear cuenta</button>
                </div>
            </form>
        </details>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Directorio</p>
                <h2>Personas con acceso</h2>
            </div>
            <span class="badge">{{ $users->count() }} resultado(s)</span>
        </div>

        <form method="get" action="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'usuarios']) }}" class="grid gap-3 lg:grid-cols-[1fr_220px_180px_auto]">
            <label class="field">
                <span>Buscar</span>
                <input name="q" value="{{ request('q') }}" placeholder="Nombre o correo">
            </label>
            <label class="field">
                <span>Rol</span>
                <select name="role">
                    <option value="">Todos los roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Estado</span>
                <select name="status">
                    <option value="">Todos</option>
                    <option value="active" @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button class="btn-secondary" type="submit">Filtrar</button>
                <a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'usuarios']) }}">Limpiar</a>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Usuario</th>
                        <th class="px-5 py-3">Correo</th>
                        <th class="px-5 py-3">Rol</th>
                        <th class="px-5 py-3">Estado</th>
                        <th class="px-5 py-3">Última actualización</th>
                        <th class="px-5 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $managerCanEdit = $isAdmin || in_array($user->role, [\App\Enums\UserRole::Member, \App\Enums\UserRole::SiidManager], true);
                            $protectedSelf = $user->is($currentUser);
                            $canEditRow = $managerCanEdit && ! ($protectedSelf && ! $isAdmin);
                        @endphp
                        <tr class="align-top">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-950">{{ $user->name }}</div>
                                @if($protectedSelf)
                                    <span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">Tu cuenta</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $user->email }}</td>
                            <td class="px-5 py-4">
                                <span class="badge">{{ $user->role->label() }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $user->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $user->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $user->updated_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end">
                                    <details class="relative">
                                        <summary class="btn-secondary cursor-pointer list-none">Gestionar</summary>
                                        <div class="mt-3 w-[min(92vw,760px)] rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-xl xl:absolute xl:right-0 xl:z-20">
                                            <form method="post" action="{{ route('admin.users.update', $user) }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                                @csrf
                                                @method('PATCH')

                                                <label class="field md:col-span-2">
                                                    <span>Nombre</span>
                                                    <input name="name" value="{{ old("users.{$user->id}.name", $user->name) }}" required @readonly(! $isAdmin)>
                                                </label>

                                                <label class="field md:col-span-2">
                                                    <span>Correo</span>
                                                    <input type="email" name="email" value="{{ old("users.{$user->id}.email", $user->email) }}" required @readonly(! $isAdmin)>
                                                </label>

                                                <label class="field md:col-span-2">
                                                    <span>Rol</span>
                                                    <select name="role" @disabled($protectedSelf || ! $managerCanEdit)>
                                                        @foreach($assignableRoles as $role)
                                                            <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if($protectedSelf || ! $managerCanEdit)
                                                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                                                    @endif
                                                </label>

                                                <label class="field md:col-span-2">
                                                    <span>Estado</span>
                                                    <select name="active" @disabled($protectedSelf)>
                                                        <option value="1" @selected($user->active)>Activo</option>
                                                        <option value="0" @selected(! $user->active)>Inactivo</option>
                                                    </select>
                                                    @if($protectedSelf)<input type="hidden" name="active" value="1">@endif
                                                </label>

                                                <div class="md:col-span-2 xl:col-span-4">
                                                    <button class="btn-primary" type="submit" @disabled(! $canEditRow)>Guardar cambios</button>
                                                </div>
                                            </form>

                                            @if($isAdmin && ! $protectedSelf)
                                                <div class="mt-4 grid gap-4 border-t border-slate-200 pt-4 lg:grid-cols-[1fr_auto]">
                                                    <details class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                                        <summary class="cursor-pointer font-semibold text-slate-800">Restablecer contraseña</summary>
                                                        <form method="post" action="{{ route('admin.users.password.update', $user) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                                                            @csrf
                                                            @method('PATCH')
                                                            <label class="field">
                                                                <span>Nueva contraseña</span>
                                                                <input type="password" name="password" minlength="12" autocomplete="new-password" required>
                                                            </label>
                                                            <label class="field">
                                                                <span>Confirmar nueva contraseña</span>
                                                                <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
                                                            </label>
                                                            <div class="md:col-span-2">
                                                                <button class="btn-secondary" type="submit">Restablecer</button>
                                                            </div>
                                                        </form>
                                                        <p class="mt-2 text-xs text-slate-500">Se revocan tokens personales activos.</p>
                                                    </details>

                                                    <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Retirar el acceso de {{ $user->name }}? La cuenta quedará inactiva.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn-danger" type="submit">Retirar acceso</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-slate-500">No hay usuarios que coincidan con los filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Matriz de permisos</p>
                <h2>Qué permite cada rol</h2>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach($roles as $role)
                <article class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-bold text-slate-950">{{ $role->label() }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $role->description() }}</p>
                    <ul class="mt-4 space-y-2 text-sm text-slate-700">
                        @foreach($role->permissions() as $permission)
                            <li class="flex gap-2"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span><span>{{ $permission }}</span></li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </section>
</div>
@endsection
