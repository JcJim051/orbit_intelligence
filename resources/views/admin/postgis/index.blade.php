@extends('layouts.app', ['title' => 'Infraestructura SIG · ActaLab'])

@section('content')
<div class="space-y-8">
    <header>
        <p class="eyebrow">Administración</p>
        <h1 class="page-title">Infraestructura SIG</h1>
        <p class="page-subtitle">Configure, pruebe y active PostgreSQL/PostGIS desde Laravel. Las contraseñas se cifran y nunca vuelven a mostrarse.</p>
    </header>

    <section class="grid gap-4 md:grid-cols-3">
        <div class="panel"><p class="text-xs font-semibold uppercase text-slate-500">Conexión actual</p><p class="mt-2 text-lg font-semibold">{{ strtoupper($currentDriver) }}</p><p class="mt-1 text-sm text-slate-600">Motor que atiende esta sesión.</p></div>
        <div class="panel"><p class="text-xs font-semibold uppercase text-slate-500">PostGIS</p><p class="mt-2 text-lg font-semibold">{{ $postgis['configured'] ? 'Configurado' : 'Pendiente' }}</p><p class="mt-1 text-sm text-slate-600">{{ $postgis['host'] ? $postgis['host'].':'.$postgis['port'].' / '.$postgis['database'] : 'Aún no se han guardado credenciales.' }}</p></div>
        <div class="panel"><p class="text-xs font-semibold uppercase text-slate-500">Corte</p><p class="mt-2 text-lg font-semibold">{{ $postgis['active'] ? 'PostgreSQL activo' : ($postgis['prepared_at'] ? 'Listo para activar' : 'Sin ejecutar') }}</p><p class="mt-1 text-sm text-slate-600">{{ $postgis['prepared_at'] ? 'Preparado '.$postgis['prepared_at'] : 'SQLite permanece como origen.' }}</p></div>
    </section>

    @if($localQgisCredentials)
        <section class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-6 text-emerald-950">
            <p class="text-xs font-bold uppercase tracking-wider">Credencial QGIS · visible una sola vez</p>
            <h2 class="mt-2 text-xl font-semibold">Conexión local lista</h2>
            <p class="mt-2 text-sm">Copie estos datos en QGIS antes de salir de esta página. La contraseña no se volverá a mostrar.</p>
            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div><dt class="font-semibold">Servidor</dt><dd class="font-mono">{{ $localQgisCredentials['host'] }}</dd></div>
                <div><dt class="font-semibold">Puerto</dt><dd class="font-mono">{{ $localQgisCredentials['port'] }}</dd></div>
                <div><dt class="font-semibold">Base de datos</dt><dd class="font-mono">{{ $localQgisCredentials['database'] }}</dd></div>
                <div><dt class="font-semibold">Usuario</dt><dd class="font-mono">{{ $localQgisCredentials['username'] }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-semibold">Contraseña</dt><dd class="break-all font-mono select-all">{{ $localQgisCredentials['password'] }}</dd></div>
            </dl>
        </section>
    @endif

    @if(app()->isLocal() && !$postgis['configured'])
        <section class="panel border-emerald-200 bg-emerald-50/50">
            <div class="panel-head"><div><h2>Prueba local automática</h2><span>Laravel genera las credenciales, inicia PostGIS en Docker y configura los usuarios. No modifica el archivo .env.</span></div></div>
            <form method="post" action="{{ route('admin.postgis.local.store') }}" class="mt-5" onsubmit="return confirm('¿Iniciar y conectar PostGIS local con Docker?')">
                @csrf
                <button class="btn-primary">Configurar PostGIS local</button>
            </form>
        </section>
    @endif

    <section class="panel">
        <div class="panel-head"><div><h2>1. Conexión y usuarios</h2><span>La prueba crea o actualiza los usuarios de aplicación, QGIS y publicación usando la cuenta administradora.</span></div></div>
        @if($postgis['configured'])
            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="font-semibold text-slate-900">Acceso para profesionales SIG</p>
                <p class="mt-1 text-sm text-slate-600">Si una contraseña se pierde o se compromete, regenérela aquí. PostgreSQL y el almacén cifrado se actualizan juntos.</p>
                <form method="post" action="{{ route('admin.postgis.qgis-credential.store') }}" class="mt-3" onsubmit="return confirm('¿Regenerar la contraseña del usuario QGIS? La contraseña anterior dejará de funcionar.')">
                    @csrf
                    <button class="btn-primary">Regenerar credencial QGIS</button>
                </form>
            </div>
        @endif
        @if($postgis['active'])
            <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Desactive PostgreSQL antes de reemplazar esta configuración.</p>
        @else
            <form method="post" action="{{ route('admin.postgis.connection.store') }}" class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                @csrf
                <label class="field lg:col-span-2"><span>Servidor</span><input name="host" value="{{ old('host', $postgis['host'] ?? '127.0.0.1') }}" required placeholder="127.0.0.1"></label>
                <label class="field"><span>Puerto</span><input type="number" name="port" value="{{ old('port', $postgis['port'] ?? 55432) }}" min="1" max="65535" required></label>
                <label class="field"><span>Base de datos</span><input name="database" value="{{ old('database', $postgis['database'] ?? 'siid_meta') }}" required></label>
                <label class="field"><span>SSL</span><select name="sslmode">@foreach(['disable' => 'Desactivado', 'allow' => 'Permitir', 'prefer' => 'Preferir', 'require' => 'Exigir', 'verify-ca' => 'Verificar CA', 'verify-full' => 'Verificación completa'] as $value => $label)<option value="{{ $value }}" @selected(old('sslmode', $postgis['sslmode'] ?? 'prefer') === $value)>{{ $label }}</option>@endforeach</select></label>

                <label class="field"><span>Usuario administrador</span><input name="admin_username" value="{{ old('admin_username', $postgis['admin_username'] ?? 'siid_owner') }}" required></label>
                <label class="field lg:col-span-2"><span>Contraseña administradora</span><input type="password" name="admin_password" minlength="16" autocomplete="new-password" required></label>
                <div></div>

                <label class="field"><span>Usuario Laravel</span><input name="app_username" value="{{ old('app_username', $postgis['app_username'] ?? 'siid_app') }}" required></label>
                <label class="field"><span>Contraseña Laravel</span><input type="password" name="app_password" minlength="16" autocomplete="new-password" required></label>
                <label class="field"><span>Usuario QGIS</span><input name="qgis_username" value="{{ old('qgis_username', $postgis['qgis_username'] ?? 'qgis_editor') }}" required></label>
                <label class="field"><span>Contraseña QGIS</span><input type="password" name="qgis_password" minlength="16" autocomplete="new-password" required></label>
                <label class="field"><span>Usuario publicación</span><input name="reader_username" value="{{ old('reader_username', $postgis['reader_username'] ?? 'geoserver_reader') }}" required></label>
                <label class="field"><span>Contraseña publicación</span><input type="password" name="reader_password" minlength="16" autocomplete="new-password" required></label>
                <button class="btn-primary md:col-span-2 lg:col-span-4">Probar conexión y guardar cifrado</button>
            </form>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>2. Preparar PostgreSQL</h2><span>Crea un respaldo de SQLite, ejecuta migraciones, copia los datos, compara conteos y materializa las capas publicadas.</span></div></div>
        <form method="post" action="{{ route('admin.postgis.preparation.store') }}" class="mt-5" onsubmit="return confirm('¿Preparar PostgreSQL y copiar todos los datos actuales de SQLite?')">@csrf<button class="btn-primary" @disabled(!$postgis['configured'] || $postgis['active'])>Preparar y verificar PostGIS</button></form>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>3. Cambio final</h2><span>La activación sólo se habilita después de una preparación completa. El retorno a SQLite queda disponible como recuperación.</span></div></div>
        <div class="mt-5 flex flex-wrap gap-3">
            @if($postgis['active'])
                <form method="post" action="{{ route('admin.postgis.activation.destroy') }}" onsubmit="return confirm('¿Volver a SQLite?')">@csrf @method('DELETE')<button class="btn-danger">Volver a SQLite</button></form>
            @else
                <form method="post" action="{{ route('admin.postgis.activation.store') }}" onsubmit="return confirm('¿Activar PostgreSQL como base principal? La sesión se reiniciará.')">@csrf<button class="btn-primary" @disabled(!$postgis['prepared_at'])>Activar PostgreSQL</button></form>
            @endif
        </div>
    </section>
</div>
@endsection
