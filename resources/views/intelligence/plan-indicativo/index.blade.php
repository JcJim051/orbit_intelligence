@extends('layouts.app', ['title' => 'Plan indicativo · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="eyebrow">Estructura plan PDD</p>
            <h1 class="page-title">Plan indicativo {{ $vigencia }}</h1>
            <p class="page-subtitle max-w-4xl">
                Edite el valor físico programado por meta producto para la vigencia seleccionada. La carga masiva actualiza la vigencia actual o crea la vigencia siguiente sin agregar columnas nuevas.
            </p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <a class="btn-secondary" href="{{ $workspaceUrl }}?vigencia={{ $vigenciaSiguiente }}">Ver {{ $vigenciaSiguiente }}</a>
            @if($canEdit)
                <a class="btn-primary" href="{{ route('intelligence.plan-indicativo.template', ['vigencia' => $vigencia]) }}">Descargar plantilla {{ $vigencia }}</a>
            @endif
        </div>
    </header>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <p class="font-semibold">Revise la información antes de continuar.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($requiresMigration ?? false)
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900">
            <p class="eyebrow text-amber-700">Requiere migración</p>
            <h2 class="mt-2 text-xl font-black">Falta crear la tabla del plan indicativo</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6">
                El código ya está disponible, pero la base de datos todavía no tiene la tabla
                <code class="rounded bg-white px-1 py-0.5">plan_indicativo_metas</code>.
                Ejecute la migración en la conexión PostGIS administrada y vuelva a cargar esta página.
            </p>
            <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">php artisan migrate --database=managed_postgis_admin</pre>
        </section>
    @else

    <section class="grid gap-4 md:grid-cols-3">
        <article class="panel">
            <p class="eyebrow">Metas producto</p>
            <p class="mt-2 text-3xl font-black">{{ number_format($metas->count(), 0, ',', '.') }}</p>
        </article>
        <article class="panel">
            <p class="eyebrow">Con programación</p>
            <p class="mt-2 text-3xl font-black">{{ number_format($programadas, 0, ',', '.') }}</p>
        </article>
        <article class="panel">
            <p class="eyebrow">Total programado</p>
            <p class="mt-2 text-3xl font-black">{{ number_format($total, 2, ',', '.') }}</p>
        </article>
    </section>

    <section class="panel">
        <form method="get" action="{{ $workspaceUrl }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="field sm:w-48">
                <span>Vigencia</span>
                <input type="number" name="vigencia" value="{{ $vigencia }}" min="2024" max="2035">
            </label>
            <button class="btn-secondary">Consultar vigencia</button>
        </form>
    </section>

    @if($canEdit)
        <section class="panel border-emerald-100 bg-emerald-50/40">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)] lg:items-end">
                <div>
                    <h2 class="text-lg font-semibold">Actualizar o crear vigencia por Excel</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        Descargue la plantilla de {{ $vigencia }}, edite <strong>valor_programado</strong> y cargue el archivo.
                        Para crear {{ $vigenciaSiguiente }}, descargue/consulte esa vigencia o cambie la columna <strong>vigencia</strong> en la matriz.
                    </p>
                </div>
                <form method="post" action="{{ route('intelligence.plan-indicativo.import') }}" enctype="multipart/form-data" class="grid gap-3">
                    @csrf
                    <input type="hidden" name="vigencia" value="{{ $vigencia }}">
                    <label class="field">
                        <span>Matriz del plan indicativo</span>
                        <input type="file" name="archivo_plan" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </label>
                    <button class="btn-primary">Cargar plan indicativo</button>
                </form>
            </div>
        </section>
    @endif

    <form method="post" action="{{ route('intelligence.plan-indicativo.update') }}">
        @csrf
        @method('patch')
        <input type="hidden" name="vigencia" value="{{ $vigencia }}">

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Programación por meta producto</h2>
                    <p class="text-sm text-slate-500">{{ number_format($metas->count(), 0, ',', '.') }} meta(s) producto en la vigencia {{ $vigencia }}.</p>
                </div>
                @if($canEdit)
                    <button class="btn-primary">Guardar cambios</button>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table data-siid-datatable data-search-placeholder="Buscar código, meta, subprograma o dependencia…" class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Meta producto</th>
                            <th class="px-4 py-3">Subprograma</th>
                            <th class="px-4 py-3" data-filter="select" data-filter-label="Dependencia">Dependencia</th>
                            <th class="px-4 py-3 text-right">Valor programado</th>
                            <th class="px-4 py-3">Observación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($metas as $meta)
                            @php($plan = $meta->planIndicativo->first())
                            <tr>
                                <td class="max-w-xl px-4 py-3 align-top">
                                    <input type="hidden" name="plan[{{ $meta->id }}][meta_producto_id]" value="{{ $meta->id }}">
                                    <span class="font-mono text-xs font-semibold text-indigo-700">{{ $meta->codigo }}</span>
                                    <p class="mt-1 font-semibold text-slate-950">{{ $meta->nombre }}</p>
                                </td>
                                <td class="px-4 py-3 align-top text-slate-600">
                                    {{ $meta->subprograma?->codigo }} — {{ $meta->subprograma?->nombre }}
                                </td>
                                <td class="px-4 py-3 align-top text-slate-600" data-filter-values="{{ $meta->dependencia?->etiqueta() ?? 'Sin dependencia' }}">
                                    {{ $meta->dependencia?->etiqueta() ?? 'Sin dependencia' }}
                                </td>
                                <td class="min-w-44 px-4 py-3 align-top text-right">
                                    @if($canEdit)
                                        <input class="text-right" type="number" step="0.0001" min="0" name="plan[{{ $meta->id }}][valor_programado]" value="{{ old('plan.'.$meta->id.'.valor_programado', $plan?->valor_programado ?? 0) }}">
                                    @else
                                        {{ number_format((float) ($plan?->valor_programado ?? 0), 4, ',', '.') }}
                                    @endif
                                </td>
                                <td class="min-w-72 px-4 py-3 align-top">
                                    @if($canEdit)
                                        <input type="text" name="plan[{{ $meta->id }}][observacion]" value="{{ old('plan.'.$meta->id.'.observacion', $plan?->observacion) }}" placeholder="Opcional">
                                    @else
                                        {{ $plan?->observacion ?: '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>
    @endif
</div>
@endsection
