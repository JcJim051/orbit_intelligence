<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $dashboard->name }} · SIID 2.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="dashboard-public-body">
    <main
        class="min-h-screen bg-slate-50"
        data-superset
        data-token-url="{{ route('dashboards.superset.public-token', $dashboard) }}"
        data-dominio="{{ config('superset.url') }}"
    ><div class="p-8 text-sm text-slate-500">Cargando tablero…</div></main>
</body>
</html>
