@php
    $number = fn ($amount) => $amount === null ? '—' : rtrim(rtrim(number_format((float) $amount, 6, ',', '.'), '0'), ',');
    $axisNumber = fn ($amount) => rtrim(rtrim(number_format((float) $amount, 2, ',', '.'), '0'), ',');
    $approvedData = $indicador->datos->where('estado', 'aprobado')->values();
    $chartValues = $approvedData->map(fn ($dato) => (float) $dato->valor);
    $chartMin = $chartValues->isEmpty() ? 0 : (float) $chartValues->min();
    $chartMax = $chartValues->isEmpty() ? 0 : (float) $chartValues->max();
    $chartRange = max($chartMax - $chartMin, 1);
    $plotLeft = 108; $plotTop = 28; $plotWidth = 752; $plotHeight = 184;
    $chartPoints = $approvedData->map(function ($dato, $index) use ($approvedData, $chartMin, $chartRange, $plotLeft, $plotTop, $plotWidth, $plotHeight) {
        $x = $plotLeft + ($approvedData->count() <= 1 ? $plotWidth / 2 : ($index * $plotWidth / ($approvedData->count() - 1)));
        $y = $plotTop + $plotHeight - ((((float) $dato->valor) - $chartMin) / $chartRange * $plotHeight);
        $tooltipWidth = 174;

        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'tooltip_x' => round(min(max($x - ($tooltipWidth / 2), 4), 900 - $tooltipWidth - 4), 2),
            'tooltip_y' => round(max($y - 58, 4), 2),
            'tooltip_width' => $tooltipWidth,
            'dato' => $dato,
        ];
    });
    $statusTone = match ($indicador->estado) {
        'activo', 'historico_validado', 'listo_habilitar' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'historico_observado' => 'bg-rose-100 text-rose-800 border-rose-200',
        'historico_cargado', 'pendiente_historico' => 'bg-amber-100 text-amber-800 border-amber-200',
        default => 'bg-indigo-100 text-indigo-800 border-indigo-200',
    };
@endphp

<style>
    .it-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
        align-items: start;
    }
    .it-hero { grid-column: span 8 / span 8; background: linear-gradient(135deg, #0f172a 0%, #312e81 58%, #4338ca 100%); color: #fff; }
    .it-hero h1, .it-hero strong { color: #fff; }
    .it-hero .it-hero-copy { color: #e0e7ff; }
    .it-hero .it-hero-muted { color: #c7d2fe; }
    .it-hero-chip { background: rgba(255,255,255,.12); color: #eef2ff; border: 1px solid rgba(255,255,255,.12); }
    .it-status { grid-column: span 4 / span 4; min-height: 100%; }
    .it-kpi { grid-column: span 3 / span 3; min-width: 0; }
    .it-chart { grid-column: span 8 / span 8; min-width: 0; }
    .it-scope { grid-column: span 4 / span 4; min-width: 0; }
    .it-management { min-width: 0; }
    .it-series { grid-column: span 8 / span 8; min-width: 0; }
    .it-technical { grid-column: span 4 / span 4; min-width: 0; }
    .it-chart-point { cursor: crosshair; }
    .it-chart-point circle { transition: r .15s ease, filter .15s ease; }
    .it-chart-point:hover circle, .it-chart-point:focus circle { r: 8px; filter: drop-shadow(0 2px 4px rgba(79,70,229,.35)); outline: none; }
    .it-chart-tooltip { opacity: 0; pointer-events: none; transition: opacity .15s ease; }
    .it-chart-point:hover .it-chart-tooltip, .it-chart-point:focus .it-chart-tooltip { opacity: 1; }
    @media (max-width: 1180px) {
        .it-hero, .it-status, .it-chart, .it-scope, .it-management, .it-series, .it-technical { grid-column: span 12 / span 12; }
        .it-kpi { grid-column: span 6 / span 6; }
    }
    @media (max-width: 640px) {
        .it-dashboard-grid { grid-template-columns: minmax(0, 1fr); }
        .it-hero, .it-status, .it-kpi, .it-chart, .it-scope, .it-management, .it-series, .it-technical { grid-column: 1 / -1; }
    }
</style>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a class="text-sm font-bold text-indigo-700 hover:underline" href="{{ route('intelligence.indicadores-territoriales.index') }}">← Volver a indicadores</a>
        <div class="flex items-center gap-2">
            <span class="rounded-full border px-3 py-1.5 text-xs font-black {{ $statusTone }}">{{ $estados[$indicador->estado] ?? $indicador->estado }}</span>
            @if($canEdit)<a class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700" href="{{ route('intelligence.indicadores-territoriales.edit', $indicador) }}">Editar ficha</a>@endif
            @if($canEdit)<a class="rounded-xl border border-indigo-300 bg-white px-4 py-2 text-xs font-bold text-indigo-700 shadow-sm hover:bg-indigo-50" href="{{ route('intelligence.indicadores-territoriales.historico.edit', $indicador) }}">Editar serie de datos</a>@endif
            @if($canValidateHistory)<form method="post" action="{{ route('intelligence.indicadores-territoriales.historico.validate-all', $indicador) }}" onsubmit="return confirm('¿Confirma que revisó y aprueba todos los cambios pendientes de la serie de datos?')">@csrf @method('PATCH')<button class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700">Aprobar cambios ({{ $historySummary['pending'] }})</button></form>@endif
            @if($canApproveTechnical)<form method="post" action="{{ route('intelligence.indicadores-territoriales.approve', $indicador) }}" onsubmit="return confirm('¿Confirma que revisó toda la ficha técnica? Al aprobarla se habilitará la carga histórica.')">@csrf @method('PATCH')<button class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700">Aprobar ficha técnica</button></form>@endif
        </div>
    </div>

    @if(session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><strong>No fue posible completar la operación.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="it-dashboard-grid">
        {{-- Identidad principal --}}
        <section class="it-hero relative overflow-hidden rounded-3xl p-6 shadow-lg">
            <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="flex flex-wrap items-center gap-2"><span class="it-hero-chip rounded-full px-3 py-1 text-xs font-black tracking-[0.18em]">{{ $indicador->codigo }}</span><span class="it-hero-chip rounded-full px-3 py-1 text-xs font-bold">{{ $periodicidades[$indicador->periodicidad] ?? $indicador->periodicidad_original ?? 'Periodicidad por definir' }}</span></div>
                <h1 class="mt-5 max-w-4xl text-2xl font-black leading-tight xl:text-3xl">{{ $indicador->nombre }}</h1>
                <p class="it-hero-copy mt-4 max-w-4xl text-sm leading-6">{{ $indicador->definicion ?: 'La definición técnica de este indicador está pendiente de completar.' }}</p>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="it-hero-chip rounded-2xl p-3"><span class="it-hero-muted text-xs uppercase tracking-wider">Dependencia</span><strong class="mt-1 block text-sm">{{ $indicador->dependencia?->nombre ?? $indicador->dependencia_fuente ?? 'Sin relacionar' }}</strong></div>
                    <div class="it-hero-chip rounded-2xl p-3"><span class="it-hero-muted text-xs uppercase tracking-wider">Enlace de información</span><strong class="mt-1 block text-sm">{{ $indicador->asignado?->name ?? 'Sin asignar' }}</strong></div>
                </div>
            </div>
        </section>

        {{-- Estado operativo --}}
        <section class="it-status rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Estado de la información</p>
            <div class="mt-5 flex items-end justify-between gap-3"><div><span class="text-sm text-slate-500">Último periodo aprobado</span><strong class="mt-1 block text-3xl font-black text-slate-950">{{ $historySummary['latest']?->periodo ?? '—' }}</strong></div><div class="text-right"><span class="text-sm text-slate-500">Valor</span><strong class="mt-1 block text-xl font-black text-indigo-700">{{ $number($historySummary['latest']?->valor) }}</strong></div></div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-emerald-500" style="width: {{ $historySummary['total'] ? round($historySummary['approved'] / $historySummary['total'] * 100) : 0 }}%"></div></div>
            <div class="mt-2 flex justify-between text-xs text-slate-500"><span>{{ $historySummary['approved'] }} validados</span><span>{{ $historySummary['pending'] }} pendientes</span></div>
            <div class="mt-5 rounded-2xl bg-slate-50 p-4"><span class="text-xs font-bold uppercase text-slate-500">Unidad de medida</span><strong class="mt-1 block text-slate-900">{{ $indicador->unidad_medida ?? 'Por definir' }}</strong></div>
        </section>

        {{-- KPI --}}
        @foreach([
            ['label' => 'Registros cargados', 'value' => $historySummary['total'], 'note' => 'periodos en la serie', 'tone' => 'text-slate-950', 'bar' => 'bg-slate-900'],
            ['label' => 'Datos aprobados', 'value' => $historySummary['approved'], 'note' => 'disponibles para análisis', 'tone' => 'text-emerald-700', 'bar' => 'bg-emerald-500'],
            ['label' => 'Pendientes', 'value' => $historySummary['pending'], 'note' => 'requieren validación', 'tone' => 'text-amber-700', 'bar' => 'bg-amber-500'],
            ['label' => 'Línea base', 'value' => $number($historySummary['first']?->valor), 'note' => $historySummary['first']?->periodo ?? 'sin periodo aprobado', 'tone' => 'text-indigo-700', 'bar' => 'bg-indigo-500'],
        ] as $metric)
            <section class="it-kpi relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="absolute inset-x-0 top-0 h-1 {{ $metric['bar'] }}"></div><span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $metric['label'] }}</span><strong class="mt-2 block text-2xl font-black {{ $metric['tone'] }}">{{ $metric['value'] }}</strong><small class="text-slate-500">{{ $metric['note'] }}</small></section>
        @endforeach

        {{-- Gráfica --}}
        <section class="it-chart rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Comportamiento histórico</p><h2 class="mt-1 text-xl font-black text-slate-950">Evolución del indicador</h2><p class="text-sm text-slate-500">Solo se representan datos aprobados por el enlace de información.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $approvedData->count() }} puntos</span></div>
            @if($chartPoints->isEmpty())
                <div class="mt-5 flex min-h-72 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 text-center text-sm text-slate-500">La gráfica aparecerá cuando exista por lo menos un periodo histórico aprobado.</div>
            @else
                <div class="mt-4 overflow-x-auto"><svg class="min-w-[680px] w-full" viewBox="0 0 900 270" role="img" aria-label="Evolución histórica de {{ $indicador->nombre }}">
                    <defs><linearGradient id="indicatorArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#6366f1" stop-opacity=".22"/><stop offset="100%" stop-color="#6366f1" stop-opacity="0"/></linearGradient></defs>
                    @for($grid = 0; $grid <= 4; $grid++) @php($gridY = $plotTop + ($grid * $plotHeight / 4))<line x1="{{ $plotLeft }}" y1="{{ $gridY }}" x2="{{ $plotLeft + $plotWidth }}" y2="{{ $gridY }}" stroke="#e2e8f0" stroke-width="1"/><text x="{{ $plotLeft - 14 }}" y="{{ $gridY + 4 }}" text-anchor="end" font-size="11" font-weight="600" fill="#64748b">{{ $axisNumber($chartMax - ($grid * $chartRange / 4)) }}</text>@endfor
                    @if($chartPoints->count() > 1)<polygon points="{{ $plotLeft.','.($plotTop + $plotHeight).' '.$chartPoints->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ').' '.($plotLeft + $plotWidth).','.($plotTop + $plotHeight) }}" fill="url(#indicatorArea)"/><polyline points="{{ $chartPoints->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ') }}" fill="none" stroke="#4f46e5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>@endif
                    @foreach($chartPoints as $index => $point)
                        <g class="it-chart-point" tabindex="0" aria-label="{{ $point['dato']->periodo }}: {{ $number($point['dato']->valor) }} {{ $indicador->unidad_medida }}">
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="6" fill="#4f46e5" stroke="white" stroke-width="3"/>
                            <g class="it-chart-tooltip">
                                <rect x="{{ $point['tooltip_x'] }}" y="{{ $point['tooltip_y'] }}" width="{{ $point['tooltip_width'] }}" height="46" rx="9" fill="#0f172a" opacity=".96"/>
                                <text x="{{ $point['tooltip_x'] + 12 }}" y="{{ $point['tooltip_y'] + 18 }}" font-size="11" font-weight="700" fill="#c7d2fe">{{ \Illuminate\Support\Str::limit($point['dato']->periodo, 22) }}</text>
                                <text x="{{ $point['tooltip_x'] + 12 }}" y="{{ $point['tooltip_y'] + 35 }}" font-size="13" font-weight="800" fill="#ffffff">{{ $number($point['dato']->valor) }} {{ $indicador->unidad_medida === 'Porcentaje' ? '%' : $indicador->unidad_medida }}</text>
                            </g>
                        </g>
                        @if($chartPoints->count() <= 12 || $index % max(1, (int) ceil($chartPoints->count() / 10)) === 0 || $loop->last)
                            <text x="{{ $point['x'] }}" y="{{ $plotTop + $plotHeight + 25 }}" text-anchor="middle" font-size="11" fill="#475569">{{ \Illuminate\Support\Str::limit($point['dato']->periodo, 12) }}</text>
                        @endif
                    @endforeach
                </svg></div>
            @endif
        </section>

        {{-- Ficha técnica: medición, fuente y alcance --}}
        <aside class="it-scope rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Ficha técnica</p><h2 class="mt-1 text-xl font-black text-slate-950">Medición y alcance</h2></div>@if($canEdit)<a class="text-xs font-bold text-indigo-700" href="{{ route('intelligence.indicadores-territoriales.edit', $indicador) }}">Editar ↗</a>@endif</div>
            <dl class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Tipo</dt><dd class="mt-1 text-sm font-bold text-slate-900">{{ $indicador->tipo_indicador ?? 'Por definir' }}</dd></div><div class="rounded-2xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Área</dt><dd class="mt-1 text-sm font-bold text-slate-900">{{ $indicador->area_tematica ?? 'Por definir' }}</dd></div><div class="rounded-2xl bg-indigo-50 p-3"><dt class="text-xs text-indigo-600">Línea base</dt><dd class="mt-1 text-sm font-black text-indigo-900">{{ $number($indicador->linea_base_valor) }}</dd></div><div class="rounded-2xl bg-indigo-50 p-3"><dt class="text-xs text-indigo-600">Periodo base</dt><dd class="mt-1 text-sm font-black text-indigo-900">{{ $indicador->linea_base_periodo ?? 'Pendiente' }}</dd></div></dl>
            <div class="my-5 border-t border-slate-200"></div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Fuente y alcance</p><dl class="mt-4 space-y-4 text-sm"><div><dt class="font-bold text-slate-500">Fuente de información</dt><dd class="mt-1 leading-6 text-slate-800">{{ $indicador->fuente_informacion ?? 'Por definir' }}</dd></div><div><dt class="font-bold text-slate-500">Responsable</dt><dd class="mt-1 leading-6 text-slate-800">{{ $indicador->responsable_informacion ?? 'Por definir' }}</dd></div><div><dt class="font-bold text-slate-500">Cobertura</dt><dd class="mt-1 leading-6 text-slate-800">{{ $indicador->cobertura_geografica ?? 'Por definir' }}</dd></div><div><dt class="font-bold text-slate-500">Desagregaciones</dt><dd class="mt-1 leading-6 text-slate-800">{{ $indicador->desagregaciones ?? 'Sin desagregaciones registradas' }}</dd></div></dl>
        </aside>

        {{-- Serie tabular --}}
        <section class="it-series overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Datos</p><h2 class="mt-1 text-xl font-black text-slate-950">Serie histórica</h2></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $indicador->datos->count() }} registro(s)</span></div>
            @if($indicador->datos->isEmpty())<div class="p-10 text-center text-sm text-slate-500">Aún no se han cargado datos históricos.</div>@else<div class="max-h-[520px] overflow-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-4 py-3">Periodo</th><th class="px-4 py-3 text-right">Valor</th><th class="px-4 py-3">Corte / fuente</th><th class="px-4 py-3">Estado</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach($indicador->datos as $dato)<tr class="align-top hover:bg-slate-50"><td class="px-4 py-3"><strong class="text-slate-950">{{ $dato->periodo }}</strong>@if($dato->periodo_inicio || $dato->periodo_fin)<small class="mt-1 block text-slate-500">{{ $dato->periodo_inicio?->format('d/m/Y') ?? '—' }} → {{ $dato->periodo_fin?->format('d/m/Y') ?? '—' }}</small>@endif</td><td class="px-4 py-3 text-right font-black text-indigo-700">{{ $number($dato->valor) }}</td><td class="max-w-xs px-4 py-3 text-slate-600"><span>{{ $dato->fecha_corte?->format('d/m/Y') ?? 'Sin corte' }}</span><small class="mt-1 block">{{ $dato->fuente ?? 'Sin fuente específica' }}</small>@if($dato->enlace_evidencia)<a class="mt-1 inline-block text-xs font-bold text-indigo-700" href="{{ $dato->enlace_evidencia }}" target="_blank" rel="noopener">Ver evidencia ↗</a>@endif</td><td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $dato->estado === 'aprobado' ? 'bg-emerald-100 text-emerald-800' : ($dato->estado === 'observado' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">{{ \App\Models\IndicadorTerritorialDato::ESTADOS[$dato->estado] ?? $dato->estado }}</span><small class="mt-1 block text-slate-500">{{ $dato->validadoPor?->name ?? $dato->reportadoPor?->name ?? 'Sistema' }}</small></td></tr>@endforeach</tbody></table></div>@endif
        </section>

        {{-- Metodología lateral --}}
        <aside class="it-technical rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Metodología</p><h2 class="mt-1 text-xl font-black text-slate-950">Cómo se interpreta el indicador</h2><div class="mt-4 space-y-3"><div class="rounded-2xl bg-slate-50 p-4"><h3 class="text-sm font-black text-slate-900">Objetivo</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $indicador->objetivo ?? 'Objetivo pendiente de documentar.' }}</p></div><div class="rounded-2xl bg-slate-50 p-4"><h3 class="text-sm font-black text-slate-900">Fórmula o regla de cálculo</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $indicador->formula_calculo ?? 'Regla de cálculo pendiente de documentar.' }}</p></div><div class="rounded-2xl bg-slate-50 p-4"><h3 class="text-sm font-black text-slate-900">Variables</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $indicador->variables_calculo ?? 'Variables pendientes de documentar.' }}</p></div>@if($indicador->observaciones_tecnicas)<div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><strong>Observaciones técnicas:</strong> {{ $indicador->observaciones_tecnicas }}</div>@endif</div></aside>
    </div>

    @if($indicador->asignaciones->isNotEmpty())<details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><summary class="cursor-pointer font-black text-slate-900">Historial de asignaciones ({{ $indicador->asignaciones->count() }})</summary><div class="mt-4 grid gap-3 md:grid-cols-2">@foreach($indicador->asignaciones as $asignacion)<div class="rounded-xl bg-slate-50 p-3 text-sm"><strong>{{ $asignacion->asignado?->name ?? 'Usuario retirado' }}</strong><span class="block text-xs text-slate-500">{{ $asignacion->asignado_at?->format('d/m/Y H:i') }} · asignó {{ $asignacion->asignador?->name ?? 'Sistema' }}</span>@if($asignacion->motivo)<p class="mt-1 text-slate-600">{{ $asignacion->motivo }}</p>@endif</div>@endforeach</div></details>@endif

    {{-- Gestión de carga: se mantiene al final de la ficha --}}
    <aside class="it-management rounded-3xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,34rem)] lg:items-start">
            <div><div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-lg font-black text-white">↥</div><p class="mt-4 text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Gestión de datos</p><h2 class="mt-1 text-xl font-black text-slate-950">Serie histórica</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Descargue la plantilla identificada para este indicador, agregue una fila por periodo y cargue el archivo diligenciado.</p><a href="{{ route('intelligence.indicadores-territoriales.historico.template', $indicador) }}" class="mt-5 inline-flex justify-center rounded-xl border border-indigo-300 bg-white px-4 py-2.5 text-sm font-bold text-indigo-700">Descargar plantilla Excel</a></div>
            <div>
                @if($canUploadHistory)<form method="post" enctype="multipart/form-data" action="{{ route('intelligence.indicadores-territoriales.historico.import', $indicador) }}" class="space-y-3">@csrf<label class="block"><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Archivo diligenciado</span><input class="block w-full rounded-xl border border-slate-300 bg-white p-2 text-sm" type="file" name="archivo_historico" accept=".xlsx" required></label><button class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Cargar históricos</button></form>@endif
            </div>
        </div>
    </aside>
</div>
