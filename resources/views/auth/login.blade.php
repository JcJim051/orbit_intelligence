@extends('layouts.app')
@section('content')
<div class="mx-auto mt-12 max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
    <p class="text-sm font-medium text-indigo-600">Acceso privado</p>
    <h1 class="mt-2 text-2xl font-semibold">Ingresa a SIID 2.0</h1>
    <p class="mt-2 text-sm text-slate-500">Solo cuentas creadas por un administrador.</p>
    <form method="post" action="{{ route('login.store') }}" class="mt-7 space-y-5">@csrf
        <label class="field"><span>Correo</span><input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
        <label class="field"><span>Contraseña</span><input type="password" name="password" required></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Recordarme</label>
        <button class="btn-primary w-full">Ingresar</button>
    </form>
</div>
@endsection
