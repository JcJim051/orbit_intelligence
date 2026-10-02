@if(($filamentEmbedded ?? false) || request()->routeIs('filament.management.pages.espacios.*'))
    @yield('content')
@else
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
            $isDedicatedOdsReviewer = auth()->user()->isDedicatedOdsReviewer();
            $inSig = request()->routeIs('admin.postgis.*', 'admin.spatial-imports.*', 'admin.spatial-datasets.*', 'admin.geo-viewers.*', 'admin.geo-layers.*', 'admin.open-data-sources.*', 'admin.dashboards.*', 'admin.data-sources.*', 'admin.indicators.*');
            $sectionTitle = match(true) {
                request()->routeIs('admin.postgis.*') => 'Configuración geográfica',
                request()->routeIs('admin.spatial-imports.*') => 'Cargas desde QGIS',
                request()->routeIs('admin.spatial-datasets.*') => 'Datos y formularios SIG',
                request()->routeIs('admin.geo-viewers.*', 'admin.geo-layers.*', 'admin.open-data-sources.*') => 'Publicación de geovisores',
                request()->routeIs('admin.dashboards.*', 'admin.data-sources.*') => 'Dashboards interactivos',
                request()->routeIs('admin.indicators.*') => 'Indicadores',
                request()->routeIs('investments.*', 'admin.investment-*') => 'Inversión pública',
                request()->routeIs('intelligence.*') => 'Seguimiento a metas',
                request()->routeIs('admin.users.*') => 'Equipo y permisos',
                request()->routeIs('admin.drive.*') => 'Integraciones',
                request()->routeIs('tokens.*') => 'Dispositivos',
                default => 'Reuniones y seguimiento',
            };
        @endphp

        @if(request()->routeIs('intelligence.*'))
            <div class="min-h-screen bg-slate-50">
                <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6">
                    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.22em] text-emerald-700">SIID 2.0 · Gestión</p>
                            <strong class="text-sm text-slate-950">{{ $sectionTitle }}</strong>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ \App\Filament\Pages\Goals::getUrl() }}">Volver a Seguimiento</a>
                            <a class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" href="{{ \App\Filament\Pages\Home::getUrl() }}">Menú completo</a>
                        </div>
                    </div>
                </header>

                <main id="contenido-principal" class="mx-auto max-w-7xl px-4 py-8 sm:px-6" tabindex="-1">
                    @if(session('status'))<div class="flash-message is-success" role="status">{{ session('status') }}</div>@endif
                    @if(session('error'))<div class="flash-message is-error" role="alert">{{ session('error') }}</div>@endif
                    @if($errors->any())<div class="flash-message is-error" role="alert"><strong>Revise la información:</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    {{ $slot ?? '' }}
                    @yield('content')
                </main>
            </div>
        @else
        <div class="app-shell" data-app-shell>
            <div class="app-sidebar-overlay" data-sidebar-overlay></div>
            <aside class="app-sidebar" id="app-sidebar" data-sidebar aria-label="Navegación principal">
                <div class="app-brand">
                    <a href="{{ route('meetings.index') }}" aria-label="Ir al inicio"><span class="app-brand-mark">M</span><span><strong>SIID 2.0</strong><small>Gestión institucional</small></span></a>
                    <button type="button" class="app-sidebar-close" data-sidebar-close aria-label="Cerrar menú">×</button>
                </div>

                <nav class="app-navigation">
                    @unless($isDedicatedOdsReviewer)
                        <div class="app-nav-group">
                            <p>Trabajo diario</p>
                            <x-sidebar-link :href="route('meetings.index')" :active="request()->routeIs('meetings.*')" badge="RE">Reuniones</x-sidebar-link>
                            <x-sidebar-link :href="route('investments.dashboard')" :active="request()->routeIs('investments.*')" badge="IP">Inversión pública</x-sidebar-link>
                        </div>
                    @endunless

                    <div class="app-nav-group">
                        <p>Seguimiento a metas</p>
                        @unless($isDedicatedOdsReviewer)
                            <x-sidebar-link :href="route('intelligence.reporte-mensual.index')" :active="request()->routeIs('intelligence.reporte-mensual.*')" badge="RM">Reporte mensual</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.dependencias.index')" :active="request()->routeIs('intelligence.dependencias.*')" badge="DP">Dependencias</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.municipios.index')" :active="request()->routeIs('intelligence.municipios.*')" badge="MU">Municipios</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.reglas-pasiva.index')" :active="request()->routeIs('intelligence.reglas-pasiva.*')" badge="RP">Reglas de pasiva</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.pilares.index')" :active="request()->routeIs('intelligence.pilares.*')" badge="PI">Pilares</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.ejes.index')" :active="request()->routeIs('intelligence.ejes.*')" badge="EJ">Ejes</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.lineas.index')" :active="request()->routeIs('intelligence.lineas.*')" badge="LI">Líneas</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.programas.index')" :active="request()->routeIs('intelligence.programas.*')" badge="PR">Programas</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.subprogramas.index')" :active="request()->routeIs('intelligence.subprogramas.*')" badge="SP">Subprogramas</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.sectores-mga.index')" :active="request()->routeIs('intelligence.sectores-mga.*')" badge="SM">Sectores MGA</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.metas-producto.index')" :active="request()->routeIs('intelligence.metas-producto.*')" badge="MP">Metas producto</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.indicadores-resultado.index')" :active="request()->routeIs('intelligence.indicadores-resultado.*')" badge="IR">Indicadores de resultado</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.metas-resultado.por-pilar')" :active="request()->routeIs('intelligence.metas-resultado.por-pilar')" badge="PP">Metas por pilar</x-sidebar-link>
                            <x-sidebar-link :href="route('intelligence.metas-resultado.index')" :active="request()->routeIs('intelligence.metas-resultado.*') && ! request()->routeIs('intelligence.metas-resultado.por-pilar')" badge="MR">Metas resultado</x-sidebar-link>
                        @endunless
                        @if(auth()->user()->canReviewOdsIndicators())<x-sidebar-link :href="route('intelligence.revision-ods.index')" :active="request()->routeIs('intelligence.revision-ods.*')" badge="OD">Revisión ODS</x-sidebar-link>@endif
                    </div>

                    @if(! $isDedicatedOdsReviewer && auth()->user()->canAccessSpatialGovernance())
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
                                <x-sidebar-link :href="route('admin.indicators.index')" :active="request()->routeIs('admin.indicators.*')" badge="IN">Indicadores</x-sidebar-link>
                            @endif
                            <x-sidebar-link :href="route('geo-viewers.demo')" badge="PC" target="_blank" rel="noopener">Portal ciudadano</x-sidebar-link>
                        </div>
                    @endif

                    @if(! $isDedicatedOdsReviewer && (auth()->user()->isAdmin() || auth()->user()->role === \App\Enums\UserRole::Manager))
                        <div class="app-nav-group">
                            <p>Administración</p>
                            <x-sidebar-link :href="\App\Filament\Resources\UserResource::getUrl()" :active="request()->routeIs('filament.management.resources.usuarios.*', 'admin.users.*')" badge="EQ">Equipo y permisos</x-sidebar-link>
                            @if(auth()->user()->isAdmin())
                                <x-sidebar-link :href="route('admin.postgis.index')" :active="request()->routeIs('admin.postgis.*')" badge="BD">Base geográfica</x-sidebar-link>
                                <x-sidebar-link :href="route('admin.investment-entities.index')" :active="request()->routeIs('admin.investment-*')" badge="CL">Clasificaciones</x-sidebar-link>
                                <x-sidebar-link :href="route('admin.drive.index')" :active="request()->routeIs('admin.drive.*')" badge="GD">Google Drive</x-sidebar-link>
                            @endif
                        </div>
                    @endif

                    @unless($isDedicatedOdsReviewer)
                        <div class="app-nav-group">
                            <p>Mi cuenta</p>
                            <x-sidebar-link :href="route('tokens.index')" :active="request()->routeIs('tokens.*')" badge="DI">Dispositivos</x-sidebar-link>
                        </div>
                    @endunless
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
        @endif
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
@endif
