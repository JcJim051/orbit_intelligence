<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'SIID 2.0' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <a href="#contenido-principal" class="skip-link">Saltar al contenido</a>

    @auth
        @php
            $inSig = request()->routeIs('admin.postgis.*', 'admin.spatial-imports.*', 'admin.spatial-datasets.*', 'admin.geo-viewers.*', 'admin.geo-layers.*', 'admin.open-data-sources.*', 'admin.dashboards.*', 'admin.data-sources.*');
            $sectionTitle = match(true) {
                request()->routeIs('admin.postgis.*') => 'Configuración geográfica',
                request()->routeIs('admin.spatial-imports.*') => 'Cargas desde QGIS',
                request()->routeIs('admin.spatial-datasets.*') => 'Datos y formularios SIG',
                request()->routeIs('admin.geo-viewers.*', 'admin.geo-layers.*', 'admin.open-data-sources.*') => 'Publicación de geovisores',
                request()->routeIs('admin.dashboards.*', 'admin.data-sources.*') => 'Dashboards interactivos',
                request()->routeIs('investments.*', 'admin.investment-*') => 'Inversión pública',
                request()->routeIs('admin.users.*') => 'Equipo y permisos',
                request()->routeIs('admin.drive.*') => 'Integraciones',
                request()->routeIs('tokens.*') => 'Dispositivos',
                default => 'Reuniones y seguimiento',
            };
        @endphp

        <div class="app-shell" data-app-shell>
            <div class="app-sidebar-overlay" data-sidebar-overlay></div>
            <aside class="app-sidebar" id="app-sidebar" data-sidebar aria-label="Navegación principal">
                <div class="app-brand">
                    <a href="{{ route('meetings.index') }}" aria-label="Ir al inicio"><span class="app-brand-mark">M</span><span><strong>SIID 2.0</strong><small>Gestión institucional</small></span></a>
                    <button type="button" class="app-sidebar-close" data-sidebar-close aria-label="Cerrar menú">×</button>
                </div>

                <nav class="app-navigation">
                    <div class="app-nav-group">
                        <p>Trabajo diario</p>
                        <x-sidebar-link :href="route('meetings.index')" :active="request()->routeIs('meetings.*')" badge="RE">Reuniones</x-sidebar-link>
                        <x-sidebar-link :href="route('investments.dashboard')" :active="request()->routeIs('investments.*')" badge="IP">Inversión pública</x-sidebar-link>
                    </div>

                    @if(auth()->user()->canAccessSpatialGovernance())
                        <div class="app-nav-group">
                            <p>Gestión geográfica</p>
                            @if(auth()->user()->isAdmin())<x-sidebar-link :href="route('admin.spatial-imports.index')" :active="request()->routeIs('admin.spatial-imports.*')" badge="QG">Cargar desde QGIS</x-sidebar-link>@endif
                            @if(auth()->user()->isAdmin() || auth()->user()->canApproveSpatialPublication() || auth()->user()->role === \App\Enums\UserRole::SiidManager)
                                <x-sidebar-link :href="route('admin.spatial-datasets.index')" :active="request()->routeIs('admin.spatial-datasets.*')" badge="DT">Datos y formularios</x-sidebar-link>
                                <x-sidebar-link :href="route('admin.geo-viewers.index')" :active="request()->routeIs('admin.geo-viewers.*', 'admin.geo-layers.*')" badge="GV">Visores y capas</x-sidebar-link>
                            @endif
                            <x-sidebar-link :href="route('admin.open-data-sources.index')" :active="request()->routeIs('admin.open-data-sources.*')" badge="DA">Datos abiertos</x-sidebar-link>
                            @if(auth()->user()->canManageDashboards())
                                <x-sidebar-link :href="route('admin.dashboards.index')" :active="request()->routeIs('admin.dashboards.*')" badge="DB">Dashboards</x-sidebar-link>
                                <x-sidebar-link :href="route('admin.data-sources.index')" :active="request()->routeIs('admin.data-sources.*')" badge="FT">Fuentes tabulares</x-sidebar-link>
                            @endif
                            <x-sidebar-link :href="route('geo-viewers.demo')" badge="PC" target="_blank" rel="noopener">Portal ciudadano</x-sidebar-link>
                        </div>
                    @endif

                    @if(auth()->user()->isAdmin() || auth()->user()->role === \App\Enums\UserRole::Manager)
                        <div class="app-nav-group">
                            <p>Administración</p>
                            <x-sidebar-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" badge="EQ">Equipo y permisos</x-sidebar-link>
                            @if(auth()->user()->isAdmin())
                                <x-sidebar-link :href="route('admin.postgis.index')" :active="request()->routeIs('admin.postgis.*')" badge="BD">Base geográfica</x-sidebar-link>
                                <x-sidebar-link :href="route('admin.investment-entities.index')" :active="request()->routeIs('admin.investment-*')" badge="CL">Clasificaciones</x-sidebar-link>
                                <x-sidebar-link :href="route('admin.drive.index')" :active="request()->routeIs('admin.drive.*')" badge="GD">Google Drive</x-sidebar-link>
                            @endif
                        </div>
                    @endif

                    <div class="app-nav-group">
                        <p>Mi cuenta</p>
                        <x-sidebar-link :href="route('tokens.index')" :active="request()->routeIs('tokens.*')" badge="DI">Dispositivos</x-sidebar-link>
                    </div>
                </nav>

                <div class="app-user-card">
                    <div class="app-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="min-w-0"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->label() }}</small></div>
                    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" title="Cerrar sesión" aria-label="Cerrar sesión">↗</button></form>
                </div>
            </aside>

            <div class="app-workspace">
                <header class="app-topbar">
                    <button type="button" class="app-menu-button" data-sidebar-open aria-controls="app-sidebar" aria-expanded="false"><span aria-hidden="true">☰</span><span class="sr-only">Abrir menú</span></button>
                    <div><span>SIID 2.0</span><strong>{{ $sectionTitle }}</strong></div>
                    @if($inSig)<a href="{{ route('geo-viewers.demo') }}" target="_blank" rel="noopener">Ver portal público <span aria-hidden="true">↗</span></a>@endif
                </header>

                <main id="contenido-principal" class="app-content" tabindex="-1">
                    @if($inSig)<x-sig-workflow />@endif
                    @if(session('status'))<div class="flash-message is-success" role="status">{{ session('status') }}</div>@endif
                    @if(session('error'))<div class="flash-message is-error" role="alert">{{ session('error') }}</div>@endif
                    @if($errors->any())<div class="flash-message is-error" role="alert"><strong>Revise la información:</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <main id="contenido-principal" class="mx-auto min-h-screen max-w-7xl px-4 py-8 sm:px-6" tabindex="-1">
            @if(session('status'))<div class="flash-message is-success" role="status">{{ session('status') }}</div>@endif
            @if(session('error'))<div class="flash-message is-error" role="alert">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="flash-message is-error" role="alert"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    @endauth

    @livewireScripts
</body>
</html>
