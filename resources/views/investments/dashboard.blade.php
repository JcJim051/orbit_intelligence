@extends('layouts.app')

@section('content')
@php
    $money = fn ($value) => $value === null ? 'Datos no reportados' : '$'.number_format((float) $value, 0, ',', '.');
    $universeLabels = ['governor' => 'Gobernación del Meta', 'territory' => 'Territorio Meta', 'ecosystem' => 'Ecosistema territorial'];
    $activeMapFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->all();
@endphp
<div class="space-y-7">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="eyebrow">Datos abiertos oficiales</p>
            <h1 class="page-title">Mapa de Inversión Pública del Meta</h1>
            <p class="page-subtitle">Consulta por BPIN, seguimiento territorial y preparación de reuniones.</p>
        </div>
        <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'proyectos', 'universe' => $filters['universe'], 'period_mode' => $filters['period_mode']]) }}" class="btn-primary">Ver portafolio</a>
    </div>

    <section class="panel border-indigo-200 bg-indigo-50/40">
        <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <p class="text-sm font-semibold text-indigo-950">Universo activo: {{ $universeLabels[$filters['universe']] }}</p>
                <p class="mt-1 text-sm text-indigo-800">
                    @if($filters['universe'] === 'governor') Entidad responsable con código 50 o nombre Meta.
                    @elseif($filters['universe'] === 'territory') Localización reportada dentro del departamento del Meta.
                    @else Unión identificable de Gobernación, territorio, entidades territoriales, Nación regionalizada y SGR. @endif
                </p>
            </div>
            <form method="get" class="flex flex-wrap gap-2">
                <input type="hidden" name="period_mode" value="{{ $filters['period_mode'] }}">
                @foreach($universeLabels as $value => $label)
                    <button name="universe" value="{{ $value }}" class="btn-small {{ $filters['universe'] === $value ? 'bg-indigo-600 text-white' : 'bg-white' }}">{{ $label }}</button>
                @endforeach
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <p class="text-sm font-semibold">Periodo de gobierno 2024–2027 · {{ config('investments.government_period.name') }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $filters['period_mode'] === 'execution' ? 'Proyectos con movimiento financiero en 2024–2027; las cifras solo suman esas vigencias.' : 'Proyectos cuyo horizonte se cruza con 2024–2027; el valor total se cuenta una vez por BPIN.' }}
                </p>
            </div>
            <form method="get" class="flex flex-wrap gap-2">
                <input type="hidden" name="universe" value="{{ $filters['universe'] }}">
                <button name="period_mode" value="execution" class="btn-small {{ $filters['period_mode'] === 'execution' ? 'bg-indigo-600 text-white' : 'bg-white' }}">Ejecución 2024–2027</button>
                <button name="period_mode" value="horizon" class="btn-small {{ $filters['period_mode'] === 'horizon' ? 'bg-indigo-600 text-white' : 'bg-white' }}">Horizonte coincidente</button>
            </form>
        </div>
    </section>

    <form method="get" class="panel grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        <input type="hidden" name="universe" value="{{ $filters['universe'] }}">
        <input type="hidden" name="period_mode" value="{{ $filters['period_mode'] }}">
        <label class="field xl:col-span-2"><span>Buscar BPIN, proyecto o entidad</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ej. hospital, vía, 202450001…"></label>
        <label class="field"><span>Municipio</span><select name="municipality"><option value="">Todo Meta</option>@foreach($filterOptions['municipalities'] as $code => $name)<option value="{{ $code }}" @selected(($filters['municipality'] ?? '') === $code)>{{ $name }}</option>@endforeach</select></label>
        <label class="field"><span>Sector</span><select name="sector"><option value="">Todos</option>@foreach($filterOptions['sectors'] as $sector)<option @selected(($filters['sector'] ?? '') === $sector)>{{ $sector }}</option>@endforeach</select></label>
        <label class="field"><span>Vigencia</span><input type="number" name="year" value="{{ $filters['year'] ?? '' }}" min="{{ config('investments.government_period.start_year') }}" max="{{ config('investments.government_period.end_year') }}" placeholder="2026"></label>
        <label class="field"><span>Fuente financiación</span><select name="funding_source"><option value="">Todas</option>@foreach($filterOptions['fundingSources'] as $source)<option @selected(($filters['funding_source'] ?? '') === $source)>{{ $source }}</option>@endforeach</select></label>
        <label class="field"><span>Estado</span><select name="status"><option value="">Todos</option>@foreach($filterOptions['statuses'] as $status)<option @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></label>
        <label class="field"><span>Entidad responsable</span><select name="entity"><option value="">Todas</option>@foreach($filterOptions['entities'] as $entity)<option @selected(($filters['entity'] ?? '') === $entity)>{{ $entity }}</option>@endforeach</select></label>
        <label class="field"><span>Tipo</span><select name="project_type"><option value="">Todos</option>@foreach(['PGN','T','SGR'] as $type)<option @selected(($filters['project_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2 xl:col-span-2"><button class="btn-primary grow">Aplicar filtros</button><a class="btn-secondary" href="{{ route('investments.dashboard', ['universe' => $filters['universe'], 'period_mode' => $filters['period_mode']]) }}">Limpiar</a></div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Proyectos BPIN únicos', number_format($summary['projects'], 0, ',', '.')],
            [$filters['period_mode'] === 'horizon' ? 'Valor total de proyectos' : 'Valor total de proyectos con ejecución', $money($summary['total_value'])],
            ['Valor vigente 2024–2027', $money($summary['current_value'])],
            ['Pagado / vigente', $summary['financial_execution_percent'] === null ? 'No calculable' : number_format($summary['financial_execution_percent'], 1, ',', '.').'%'],
            ['Comprometido', $money($summary['committed_value'])],
            ['Obligado', $money($summary['obligated_value'])],
            ['Pagado', $money($summary['paid_value'])],
            ['Avance físico medio reportado', $summary['physical_progress_percent'] === null ? 'No reportado' : number_format((float)$summary['physical_progress_percent'], 1, ',', '.').'%'],
        ] as [$label, $value])
            <article class="panel"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold tracking-tight">{{ $value }}</p></article>
        @endforeach
    </div>

    <section>
        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
            <div><p class="eyebrow">Sector descentralizado</p><h2 class="mt-1 text-2xl font-semibold tracking-tight">Entidades de la Gobernación del Meta</h2><p class="mt-1 text-sm text-slate-500">Las cifras oficiales usan asignaciones principales confirmadas; las sugerencias esperan revisión humana.</p></div>
            @if(auth()->user()->isAdmin())<a class="btn-secondary" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'clasificaciones']) }}">Revisar clasificaciones</a>@endif
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($decentralizedEntities as $entity)
                @php($entityMetrics = $entity->metrics)
                <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'entidad', 'record' => $entity->slug, 'period_mode' => $filters['period_mode']]) }}" class="panel transition hover:border-indigo-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ $entity->acronym ?: 'Entidad' }}</p><h3 class="mt-1 font-semibold leading-tight">{{ $entity->name }}</h3></div><span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">{{ number_format($entityMetrics['projects']) }}</span></div>
                    <dl class="mt-4 space-y-2 text-xs"><div class="flex justify-between gap-2"><dt class="text-slate-500">Vigente</dt><dd class="font-semibold">{{ $money($entityMetrics['current_value']) }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-500">Pagado</dt><dd class="font-semibold">{{ $money($entityMetrics['paid_value']) }}</dd></div><div class="flex justify-between gap-2"><dt class="text-slate-500">Ejecución</dt><dd class="font-semibold">{{ $entityMetrics['financial_execution_percent'] === null ? 'No calculable' : number_format($entityMetrics['financial_execution_percent'], 1, ',', '.').'%' }}</dd></div></dl>
                    @if($entity->suggested_projects > 0)<p class="mt-3 text-xs font-medium text-amber-700">{{ $entity->suggested_projects }} sugerencias pendientes</p>@endif
                </a>
            @endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <div class="panel-head"><div><h2>Sectores</h2><span>BPIN únicos; el valor total se suma una sola vez por proyecto.</span></div></div>
            <div class="mt-4 space-y-3">
                @forelse($sectors as $sector)
                    <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'proyectos', 'universe' => $filters['universe'], 'period_mode' => $filters['period_mode'], 'sector' => $sector->sector]) }}" class="grid grid-cols-[1fr_auto] gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <span class="text-sm font-medium">{{ $sector->sector }}</span><span class="text-sm text-slate-500">{{ number_format($sector->projects) }}</span>
                    </a>
                @empty <p class="text-sm text-slate-500">Sin datos sincronizados.</p> @endforelse
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><div><h2>Estados</h2><span>Clasificación publicada por la fuente.</span></div></div>
            <div class="mt-4 space-y-3">
                @forelse($statuses as $status)
                    <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'proyectos', 'universe' => $filters['universe'], 'period_mode' => $filters['period_mode'], 'status' => $status->status]) }}" class="flex justify-between gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <span class="text-sm font-medium">{{ $status->status }}</span><span class="text-sm text-slate-500">{{ number_format($status->projects) }}</span>
                    </a>
                @empty <p class="text-sm text-slate-500">Sin datos sincronizados.</p> @endforelse
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-head">
            <div><h2>Mapa territorial · 29 municipios</h2><span>Seleccione un municipio para abrir sus proyectos sin multiplicar el valor departamental.</span></div>
            <label class="field max-w-xs"><span>Colorear por</span><select data-investment-map-metric><option value="project_count">Proyectos</option><option value="current_value">Valor vigente</option><option value="paid_value">Pagado</option><option value="financial_execution_percent">Ejecución</option><option value="physical_progress_percent">Avance físico</option></select></label>
        </div>
        <div
            data-investment-map="{{ route('investments.map', $activeMapFilters) }}"
            class="mt-5 h-[32rem] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100"
            role="img"
            aria-label="Mapa de proyectos de inversión pública por municipio del Meta"
        ></div>
        <p data-investment-map-status class="mt-2 text-xs text-slate-500">Cargando límites municipales oficiales…</p>
        <details class="mt-5">
            <summary class="cursor-pointer text-sm font-semibold text-indigo-700">Ver lista de los 29 municipios</summary>
        <div class="mt-5 grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @foreach($municipalities as $municipality)
                <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'proyectos'] + array_merge($activeMapFilters, ['municipality' => $municipality['code']])) }}" class="group rounded-xl border border-slate-200 p-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                    <span class="block text-sm font-semibold group-hover:text-indigo-700">{{ $municipality['name'] }}</span>
                    <span class="mt-1 block text-xs text-slate-500">{{ $municipality['code'] }} · {{ $municipality['projects'] }} proyectos</span>
                    <span class="mt-1 block text-xs text-slate-500">Vigente {{ $money($municipality['current_value']) }} · pagado {{ $money($municipality['paid_value']) }}</span>
                </a>
            @endforeach
        </div>
        </details>
        <p class="mt-4 text-xs text-slate-500">Los proyectos sin municipio o de alcance departamental/multiterritorial se conservan aparte y no se replican en los totales municipales.</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="panel">
            <div class="panel-head"><div><h2>Fuentes de financiación</h2><span>Disponibilidad publicada por proyecto.</span></div></div>
            <div class="mt-4 space-y-2">
                @forelse($fundingSources as $source)
                    <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'proyectos', 'universe' => $filters['universe'], 'period_mode' => $filters['period_mode'], 'funding_source' => $source->funding_source]) }}" class="flex justify-between gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><span class="truncate text-sm font-medium">{{ $source->funding_source }}</span><span class="text-sm text-slate-500">{{ number_format($source->projects) }}</span></a>
                @empty <p class="text-sm text-slate-500">Sin datos sincronizados.</p> @endforelse
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><div><h2>Vigencias</h2><span>Proyectos con fila financiera en cada año.</span></div></div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                @forelse($years as $year)
                    <a href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'proyectos', 'universe' => $filters['universe'], 'period_mode' => $filters['period_mode'], 'year' => $year->fiscal_year]) }}" class="flex justify-between gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><span class="text-sm font-medium">{{ $year->fiscal_year }}</span><span class="text-sm text-slate-500">{{ number_format($year->projects) }}</span></a>
                @empty <p class="text-sm text-slate-500">Sin datos sincronizados.</p> @endforelse
            </div>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <section class="panel">
            <h2 class="text-lg font-semibold">Calidad y trazabilidad</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs text-slate-500">Registros con faltantes clave</dt><dd class="mt-1 text-xl font-semibold">{{ number_format($qualityCount) }}</dd></div>
                <div><dt class="text-xs text-slate-500">Última consulta</dt><dd class="mt-1 text-sm font-semibold">{{ $latestSnapshot?->queried_at?->format('d/m/Y H:i') ?? 'Nunca' }}</dd></div>
                <div><dt class="text-xs text-slate-500">Fecha de corte reportada</dt><dd class="mt-1 text-sm font-semibold">{{ $latestSnapshot?->cutoff_at?->format('d/m/Y H:i') ?? 'No reportada' }}</dd></div>
            </dl>
            @if($latestRun)<p class="mt-4 text-xs text-slate-500">Última carga: {{ $latestRun->status }} · {{ number_format($latestRun->projects_touched) }} proyectos · {{ number_format($latestRun->rows_written) }} filas escritas · {{ number_format($latestRun->classification_confirmed) }} clasificados · {{ number_format($latestRun->classification_suggested) }} sugeridos · {{ number_format($latestRun->classification_unclassified) }} por clasificar.</p>@endif
            <div class="mt-5 grid gap-2 md:grid-cols-2">
                @foreach($sourceSnapshots as $snapshot)
                    <a class="rounded-xl border border-slate-200 p-3 text-xs hover:bg-slate-50" href="https://www.datos.gov.co/d/{{ $snapshot->dataset_id }}" target="_blank" rel="noopener noreferrer">
                        <span class="block font-semibold text-slate-700">{{ $snapshot->dataset_name }}</span>
                        <span class="mt-1 block text-slate-500">{{ $snapshot->dataset_id }} · {{ $snapshot->status }} · {{ $snapshot->queried_at?->format('d/m/Y H:i') }}</span>
                        @if($snapshot->warning)<span class="mt-1 block text-amber-700">{{ $snapshot->warning }}</span>@endif
                    </a>
                @endforeach
            </div>
            @if($activeWarnings->isNotEmpty())
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <p class="font-semibold">Advertencias de fuentes abiertas</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($activeWarnings as $warning)<li>{{ $warning->dataset_id }}: {{ $warning->warning }}</li>@endforeach</ul>
                </div>
            @endif
        </section>
        @if(auth()->user()->isAdmin())
            <section class="panel">
                <h2 class="text-lg font-semibold">Actualizar fuentes</h2>
                <p class="mt-1 text-sm text-slate-500">La carga se ejecuta en segundo plano y puede repetirse sin duplicar claves naturales.</p>
                <form method="post" action="{{ route('admin.investments.sync') }}" class="mt-4 space-y-3">@csrf
                    <select name="universe"><option value="governor">Gobernación</option><option value="territory">Territorio</option><option value="ecosystem" selected>Ecosistema</option></select>
                    <input type="number" name="project_limit" min="0" max="50000" value="{{ config('investments.default_limit') }}" aria-label="Máximo de proyectos">
                    <p class="text-xs text-slate-500">Use 0 para traer todo el universo Meta filtrado por las fuentes oficiales.</p>
                    <button class="btn-primary w-full">Encolar actualización</button>
                </form>
            </section>
        @endif
    </div>
</div>
@endsection
