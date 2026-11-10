@extends('layouts.app', ['title' => 'Cuatrienio · SIID 2.0'])

@php($money = fn ($v) => '$'.number_format((float) $v, 2, ',', '.'))

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class="space-y-6">
    <header class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Seguimiento cuatrienal</p>
            <h1 class="page-title">Cuatrienio</h1>
            <p class="page-subtitle max-w-3xl">Avance acumulado de las metas producto frente al estándar del plan plurianual.</p>
        </div>
        <form method="get" class="flex items-end gap-2">
            <label class="field"><span>Vigencia</span><select name="vigencia" onchange="this.form.submit()"><option value="">Todas</option>@foreach($vigencias as $item)<option value="{{ $item }}" @selected($vigencia === (int) $item)>{{ $item }}</option>@endforeach</select></label>
            <input type="hidden" name="tab" value="{{ $tab }}">
        </form>
    </header>

    <nav class="flex gap-2 border-b border-slate-200" aria-label="Pestañas cuatrienio">
        <a class="{{ $tab === 'datos' ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-slate-500' }} px-4 py-3 font-semibold" href="{{ \App\Filament\Pages\Workspace::getUrl(['workspace' => 'cuatrienio']) }}{{ $vigencia ? '?vigencia='.$vigencia : '' }}">Datos</a>
        <a class="{{ $tab === 'analitica' ? 'border-b-2 border-emerald-600 text-emerald-700' : 'text-slate-500' }} px-4 py-3 font-semibold" href="{{ request()->fullUrlWithQuery(['tab' => 'analitica']) }}">Analítica</a>
    </nav>

    @if($filas->isEmpty())
        <section class="panel border-amber-200 bg-amber-50 text-amber-950">
            <h2 class="font-semibold">{{ $buscar !== '' ? 'No hay resultados para la búsqueda.' : 'Aún no hay filas cargadas en el consolidado del cuatrienio.' }}</h2>
            @if($buscar === '')<p class="mt-1 text-sm">La estructura queda preparada para cargar la matriz histórica. Las filas de obligado y pagado se mostrarán como <strong>No reportado</strong> cuando la fuente no las contenga.</p>@endif
        </section>
    @endif

    @if($tab === 'analitica')
        <section class="grid gap-4 md:grid-cols-3">
            @foreach($resumen as $year => $item)
                <article class="panel"><p class="eyebrow">{{ $year }}</p><p class="mt-2 text-3xl font-black">{{ number_format($item['metas'], 0, ',', '.') }}</p><p class="text-sm text-slate-500">metas producto</p><dl class="mt-4 grid grid-cols-2 gap-2 text-sm"><div><dt class="text-slate-500">Ejecutado físico</dt><dd class="font-semibold">{{ number_format($item['ejecutado'], 2, ',', '.') }}</dd></div><div><dt class="text-slate-500">% físico</dt><dd class="font-semibold">{{ $item['programado'] > 0 ? number_format(min(100, $item['ejecutado'] / $item['programado'] * 100), 1, ',', '.') : '—' }} %</dd></div><div><dt class="text-slate-500">Comprometido</dt><dd class="font-semibold">{{ $money($item['comprometido']) }}</dd></div><div><dt class="text-slate-500">Obligado / pagado</dt><dd class="font-semibold">{{ $year < 2026 ? 'No reportado' : $money($item['obligado_reportado']).' / '.$money($item['pagado_reportado']) }}</dd></div></dl></article>
            @endforeach
        </section>
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <a href="{{ request()->fullUrlWithQuery(['filtro' => '', 'dependencia' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica" class="panel hover:border-emerald-300"><p class="eyebrow">Acumulado a {{ $analitica['vigencia'] }}</p><p class="mt-2 text-3xl font-black">{{ number_format($analitica['promedio'], 1, ',', '.') }} %</p><p class="text-sm text-slate-500">avance promedio del cuatrienio · ver detalle</p></a>
            <a href="{{ request()->fullUrlWithQuery(['filtro' => 'cero', 'dependencia' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica" class="panel border-rose-200 bg-rose-50 hover:border-rose-400"><p class="eyebrow text-rose-700">Metas en cero</p><p class="mt-2 text-3xl font-black text-rose-900">{{ number_format($analitica['en_cero'], 0, ',', '.') }}</p><p class="text-sm text-rose-800">clic para ver las metas</p></a>
            <a href="{{ request()->fullUrlWithQuery(['filtro' => 'dificiles', 'dependencia' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica" class="panel border-amber-200 bg-amber-50 hover:border-amber-400"><p class="eyebrow text-amber-700">Difíciles de cumplir</p><p class="mt-2 text-3xl font-black text-amber-900">{{ number_format($analitica['dificiles'], 0, ',', '.') }}</p><p class="text-sm text-amber-800">avance entre 0 % y 30 %</p></a>
            <a href="{{ request()->fullUrlWithQuery(['filtro' => 'cumplidas', 'dependencia' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica" class="panel border-emerald-200 bg-emerald-50 hover:border-emerald-400"><p class="eyebrow text-emerald-700">Cumplidas</p><p class="mt-2 text-3xl font-black text-emerald-900">{{ number_format($analitica['cumplidas'], 0, ',', '.') }}</p><p class="text-sm text-emerald-800">avance igual o superior al 80 %</p></a>
            <a href="{{ request()->fullUrlWithQuery(['filtro' => 'sin_programacion', 'dependencia' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica" class="panel hover:border-emerald-300"><p class="eyebrow">Sin meta cuatrienal</p><p class="mt-2 text-3xl font-black">{{ number_format($analitica['sin_programacion'], 0, ',', '.') }}</p><p class="text-sm text-slate-500">clic para revisar el estándar</p></a>
        </section>

        <section class="grid items-stretch gap-6 xl:grid-cols-2">
            @php($dependenciasPorPagina = 10)
            @php($paginasDependencias = max(1, (int) ceil(count($analitica['por_dependencia']) / $dependenciasPorPagina)))
            <article class="panel flex h-full flex-col" x-data="{ pagina: 1, totalPaginas: {{ $paginasDependencias }} }">
                <h2 class="text-lg font-semibold">Avance promedio por dependencia</h2>
                <p class="mt-1 text-sm text-slate-500">Todas las dependencias, ordenadas desde el menor avance.</p>
                <div class="mt-5 flex-1 space-y-4">
                    @forelse($analitica['por_dependencia'] as $indice => $item)
                        <a x-cloak x-show="pagina === {{ intdiv($indice, $dependenciasPorPagina) + 1 }}" href="{{ request()->fullUrlWithQuery(['dependencia' => $item['nombre'], 'filtro' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica" class="block hover:opacity-80"><div class="mb-1 flex justify-between gap-3 text-sm"><span class="truncate">{{ $item['nombre'] }}</span><strong>{{ $item['promedio'] === null ? 'Sin dato' : number_format($item['promedio'], 1, ',', '.').' %' }}</strong></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $item['promedio'] === null ? 'bg-slate-300' : ($item['promedio'] < 30 ? 'bg-rose-500' : ($item['promedio'] < 60 ? 'bg-amber-500' : 'bg-emerald-500')) }}" style="width: {{ $item['promedio'] === null ? 0 : min(100, max(0, $item['promedio'])) }}%"></div></div><p class="mt-1 text-xs text-slate-500">{{ number_format($item['metas_evaluables'], 0, ',', '.') }} de {{ number_format($item['metas'], 0, ',', '.') }} metas evaluables</p></a>
                    @empty <p class="text-sm text-slate-500">No hay datos suficientes para graficar.</p> @endforelse
                </div>
                @if($paginasDependencias > 1)
                    <nav class="mt-5 flex items-center justify-between border-t border-slate-200 pt-4" aria-label="Paginación de dependencias">
                        <button type="button" class="btn-secondary" x-on:click="pagina--" x-bind:disabled="pagina === 1">Anterior</button>
                        <span class="text-sm text-slate-500">Página <strong x-text="pagina"></strong> de {{ $paginasDependencias }}</span>
                        <button type="button" class="btn-secondary" x-on:click="pagina++" x-bind:disabled="pagina === totalPaginas">Siguiente</button>
                    </nav>
                @endif
            </article>
            <article class="panel h-full">
                <h2 class="text-lg font-semibold">Metas con mayor riesgo</h2>
                <p class="mt-1 text-sm text-slate-500">Prioridad de revisión del avance acumulado a {{ $analitica['vigencia'] }}.</p>
                <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2 pr-3">Meta</th><th class="py-2 pr-3">Dependencia</th><th class="py-2 text-right">Avance</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($analitica['criticas'] as $item)<tr><td class="max-w-sm py-2 pr-3"><p class="font-semibold">{{ $item['codigo'] }}</p><p class="truncate text-xs text-slate-500">{{ $item['nombre'] }}</p></td><td class="max-w-xs truncate py-2 pr-3 text-xs">{{ $item['dependencia'] }}</td><td class="py-2 text-right font-semibold {{ ($item['porcentaje'] ?? 0) < 30 ? 'text-rose-700' : 'text-amber-700' }}">{{ $item['porcentaje'] === null ? '—' : number_format($item['porcentaje'], 1, ',', '.') }} %</td></tr>@empty<tr><td colspan="3" class="py-4 text-slate-500">No hay metas evaluables.</td></tr>@endforelse</tbody></table></div>
            </article>
        </section>
        <section id="detalle-analitica" class="panel">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div><h2 class="text-lg font-semibold">Detalle analítico</h2><p class="text-sm text-slate-500">{{ $filtro ? 'Filtro: '.$filtro : ($dependenciaFiltro ? 'Dependencia: '.$dependenciaFiltro : 'Todas las metas evaluadas') }}</p></div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <form method="get" class="flex items-end gap-2">
                        <input type="hidden" name="tab" value="analitica">
                        @if($vigencia)<input type="hidden" name="vigencia" value="{{ $vigencia }}">@endif
                        @if($filtro)<input type="hidden" name="filtro" value="{{ $filtro }}">@endif
                        @if($dependenciaFiltro)<input type="hidden" name="dependencia" value="{{ $dependenciaFiltro }}">@endif
                        <label class="field"><span>Buscar meta</span><input type="search" name="buscar" value="{{ $buscar }}" placeholder="Código, nombre o dependencia"></label>
                        <button class="btn-secondary">Buscar</button>
                    </form>
                    <a class="btn-secondary" href="{{ request()->fullUrlWithQuery(['filtro' => null, 'dependencia' => null, 'buscar' => null, 'pagina_detalle' => null]) }}#detalle-analitica">Limpiar</a>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2 pr-3">Meta</th><th class="py-2 pr-3">Dependencia</th><th class="py-2 text-right">Meta cuatrienio</th><th class="py-2 text-right">Programado al corte</th><th class="py-2 text-right">Ejecutado acumulado</th><th class="py-2 text-right">Avance cuatrienio</th><th class="py-2 text-right">Cumplimiento al corte</th><th class="py-2">Motivo / alerta</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($detalleAnalitica as $item)<tr><td class="py-2 pr-3"><span class="font-semibold">{{ $item['codigo'] }}</span><p class="max-w-xl truncate text-xs text-slate-500">{{ $item['nombre'] }}</p></td><td class="py-2 pr-3 text-xs">{{ $item['dependencia'] }}</td><td class="py-2 text-right">{{ $item['programado'] === null ? '—' : number_format($item['programado'], 2, ',', '.') }}</td><td class="py-2 text-right">{{ number_format($item['programado_al_corte'], 2, ',', '.') }}</td><td class="py-2 text-right">{{ number_format($item['ejecutado'], 2, ',', '.') }}</td><td class="py-2 text-right font-semibold">{{ $item['porcentaje'] === null ? '—' : number_format($item['porcentaje'], 1, ',', '.').' %' }}</td><td class="py-2 text-right">{{ $item['cumplimiento_al_corte'] === null ? '—' : number_format($item['cumplimiento_al_corte'], 1, ',', '.').' %' }}</td><td class="py-2 text-xs font-semibold {{ str_contains($item['motivo'], 'bajo') || str_contains($item['motivo'], 'Sin') ? 'text-rose-700' : ($item['motivo'] === 'Avance en riesgo' ? 'text-amber-700' : 'text-slate-600') }}">{{ $item['motivo'] }}</td></tr>@empty<tr><td colspan="8" class="py-5 text-center text-slate-500">No hay metas para este filtro.</td></tr>@endforelse</tbody></table></div>
            @if($detalleAnalitica->hasPages())<div class="mt-4 border-t border-slate-200 pt-4">{{ $detalleAnalitica->links() }}</div>@endif
        </section>
        <section class="panel"><h2 class="text-lg font-semibold">Criterio de lectura</h2><p class="mt-2 text-sm text-slate-600">El avance del cuatrienio compara la ejecución acumulada hasta {{ $analitica['vigencia'] }} con la meta total del plan. Las metas de tipo <strong>Suma</strong> consolidan las vigencias; las metas constantes, crecientes, decrecientes o no acumulables usan el último valor observado. El cumplimiento al corte compara ese avance con la programación que debía alcanzarse a la fecha.</p></section>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <form method="get" class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row sm:items-end">
                <input type="hidden" name="tab" value="datos">
                @if($vigencia)<input type="hidden" name="vigencia" value="{{ $vigencia }}">@endif
                <label class="field flex-1"><span>Buscar</span><input type="search" name="buscar" value="{{ $buscar }}" placeholder="Código, meta o dependencia"></label>
                <button class="btn-secondary">Buscar</button>
                @if($buscar !== '')<a class="btn-secondary" href="{{ request()->fullUrlWithQuery(['buscar' => null, 'page' => null]) }}">Limpiar</a>@endif
            </form>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Meta producto</th><th class="px-4 py-3">Vigencia</th><th class="px-4 py-3 text-right">Programado</th><th class="px-4 py-3 text-right">Ejecutado</th><th class="px-4 py-3 text-right">Asignado</th><th class="px-4 py-3 text-right">Comprometido</th><th class="px-4 py-3 text-right">Obligado</th><th class="px-4 py-3 text-right">Pagado</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($filas as $fila)<tr><td class="px-4 py-3"><p class="font-semibold">{{ $fila->codigo_meta_producto }} — {{ $fila->metaProducto?->nombre ?? 'Meta no vinculada' }}</p><p class="text-xs text-slate-500">{{ $fila->metaProducto?->dependencia?->nombre ?? $fila->responsable ?? 'Sin responsable' }}</p></td><td class="px-4 py-3">{{ $fila->vigencia }}</td><td class="px-4 py-3 text-right">{{ $fila->programado_fisico === null ? '—' : number_format($fila->programado_fisico, 2, ',', '.') }}</td><td class="px-4 py-3 text-right">{{ $fila->ejecutado_fisico === null ? '—' : number_format($fila->ejecutado_fisico, 2, ',', '.') }}</td><td class="px-4 py-3 text-right">{{ $fila->asignado === null ? '—' : $money($fila->asignado) }}</td><td class="px-4 py-3 text-right">{{ $fila->comprometido === null ? '—' : $money($fila->comprometido) }}</td><td class="px-4 py-3 text-right">{{ $fila->obligado === null ? 'No reportado' : $money($fila->obligado) }}</td><td class="px-4 py-3 text-right">{{ $fila->pagado === null ? 'No reportado' : $money($fila->pagado) }}</td></tr>@empty<tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No hay filas para mostrar.</td></tr>@endforelse</tbody></table></div>
            @if($filas->hasPages())<div class="border-t border-slate-200 p-4">{{ $filas->links() }}</div>@endif
        </section>
    @endif
</div>
<script>
    (() => {
        const navigation = performance.getEntriesByType('navigation')[0];
        if (navigation?.type === 'reload' && window.location.hash) {
            const url = new URL(window.location.href);
            url.hash = '';
            window.history.replaceState({}, document.title, url.toString());
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        }
    })();
</script>
@endsection
