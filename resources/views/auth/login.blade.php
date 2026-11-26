@extends('layouts.app')
@section('content')
<div class="login-composition">
    <div class="login-identity" aria-hidden="true">
        <img src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="">
        <p>Departamento Administrativo<br>de Planeación</p>
    </div>
<div class="login-card">
    <p class="text-sm font-medium text-blue-700">Acceso privado</p>
    <h1 class="mt-2 text-2xl font-semibold">Ingresa a SIID 2.0</h1>
    <p class="mt-2 text-sm text-slate-500">Solo cuentas creadas por un administrador.</p>
    <form method="post" action="{{ route('login.store') }}" class="mt-7 space-y-5">@csrf
        <label class="field"><span>Correo</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" autofocus></label>
        <label class="field"><span>Contraseña</span><input type="password" name="password" autocomplete="current-password" required></label>
        <div class="login-actions">
            <label class="login-remember" for="remember"><input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Recordarme</span></label>
            <button type="submit" class="login-submit">Ingresar</button>
        </div>
    </form>
</div>
</div>
@endsection
