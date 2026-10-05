@extends('layouts.app', ['title' => 'Importar avance consolidado · '.$seguimiento->etiqueta().' · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Reporte mensual de metas</p>
            <h1 class="page-title">Importar avance consolidado por meta</h1>
            <p class="page-subtitle max-w-3xl">
                Seguimiento {{ $seguimiento->etiqueta() }} · corte {{ $seguimiento->fecha_corte->format('d/m/Y') }}.
                La carga histórica validada no exige evidencia y deja los reportes en estado Reportado.
            </p>
        </div>
        <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Volver al seguimiento</a>
    </header>

    <section class="panel">
        <div class="grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="text-lg font-semibold">Subir archivo validado</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Descargue la plantilla prediligenciada del seguimiento, complete los avances físicos y financieros,
                    y vuelva a subirla en esta pantalla. SIID usará el cruce BPIN ↔ dependencia ↔ fuente ↔ meta producto
                    para cargar el histórico sin duplicar registros.
                </p>
                <a class="btn-secondary mt-4 inline-flex" href="{{ route('intelligence.reporte-mensual.historicas.plantilla', $seguimiento) }}">
                    Descargar plantilla prediligenciada
                </a>
            </div>
            <form method="post" action="{{ route('intelligence.reporte-mensual.historicas.diagnosticar', $seguimiento) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <label class="field">
                    <span>Archivo de avance consolidado diligenciado</span>
                    <input type="file" name="archivo" accept=".xlsx,.csv,.txt" required>
                </label>
                @error('archivo')
                    <p class="text-sm font-semibold text-red-700">{{ $message }}</p>
                @enderror
                <button class="btn-primary">Diagnosticar archivo</button>
                <p class="text-xs text-slate-500">
                    Si una meta no tiene BPIN o tiene varios BPIN posibles, quedará bloqueada en diagnóstico para evitar distribuir avances sin una regla clara.
                </p>
            </form>
        </div>
    </section>

    @if($carga)
        @php($diagnostico = $carga->diagnostico ?? ['filas' => [], 'summary' => []])
        @php($filas = collect($diagnostico['filas'] ?? []))
        <section class="grid gap-4 md:grid-cols-4">
            <article class="panel">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Filas leídas</p>
                <p class="mt-2 text-3xl font-black">{{ number_format($carga->filas_total, 0, ',', '.') }}</p>
            </article>
            <article class="panel">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Válidas</p>
                <p class="mt-2 text-3xl font-black text-emerald-700">{{ number_format($carga->filas_validas, 0, ',', '.') }}</p>
            </article>
            <article class="panel">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bloqueadas</p>
                <p class="mt-2 text-3xl font-black text-amber-700">{{ number_format($carga->filas_bloqueadas, 0, ',', '.') }}</p>
            </article>
            <article class="panel">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Estado</p>
                <p class="mt-2 text-2xl font-black">{{ ucfirst($carga->status) }}</p>
            </article>
        </section>

        <section class="panel">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-lg font-semibold">{{ $carga->nombre_original }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Cargado por {{ $carga->usuario?->name ?? 'Sin usuario' }} el {{ $carga->created_at->format('d/m/Y H:i') }}.
                        @if($carga->imported_at)
                            Importado el {{ $carga->imported_at->format('d/m/Y H:i') }}: {{ $carga->filas_importadas }} nuevas, {{ $carga->filas_actualizadas }} actualizadas.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($carga->filas_bloqueadas > 0)
                        <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.historicas.errores', [$seguimiento, $carga]) }}">Descargar errores CSV</a>
                    @endif
                    @if($carga->filas_validas > 0)
                        <form method="post" action="{{ route('intelligence.reporte-mensual.historicas.importar', [$seguimiento, $carga]) }}" onsubmit="return confirm('Se importarán o actualizarán las filas válidas. Las bloqueadas no se tocarán. ¿Continuar?')">
                            @csrf
                            <button class="btn-primary">{{ $carga->status === 'importado' ? 'Reimportar / actualizar' : 'Importar filas válidas' }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-semibold">Diagnóstico por fila</h2>
                <p class="text-sm text-slate-500">Solo las filas válidas pasan a actividades, avances y ejecución financiera.</p>
            </div>
            <div class="overflow-x-auto">
                <table data-siid-datatable class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Fila</th>
                            <th class="px-4 py-3">Código meta</th>
                            <th class="px-4 py-3">Meta producto</th>
                            <th class="px-4 py-3">Responsable</th>
                            <th class="px-4 py-3">Cruce</th>
                            <th class="px-4 py-3 text-right">Prog. física</th>
                            <th class="px-4 py-3 text-right">Avance físico</th>
                            <th class="px-4 py-3 text-right">Comprometido</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($filas as $fila)
                            <tr>
                                <td class="px-4 py-3">{{ $fila['fila'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $fila['codigo_meta_producto'] }}</td>
                                <td class="max-w-sm px-4 py-3">
                                    <p class="font-semibold text-slate-950">{{ $fila['meta_producto'] ?: 'Sin nombre' }}</p>
                                    @if(! empty($fila['observaciones']))
                                        <p class="mt-1 text-xs text-slate-500">{{ $fila['observaciones'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $fila['responsable'] }}</td>
                                <td class="px-4 py-3">
                                    @if(($fila['estado'] ?? '') === 'valida')
                                        <p class="font-semibold text-slate-900">BPIN {{ $fila['bpin'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $fila['dependencia'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $fila['fuente'] }}</p>
                                    @else
                                        <ul class="list-disc space-y-1 pl-4 text-xs text-red-700">
                                            @foreach(($fila['errores'] ?? []) as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if(! empty($fila['notas']))
                                        <ul class="mt-1 list-disc space-y-1 pl-4 text-xs text-amber-700">
                                            @foreach($fila['notas'] as $nota)
                                                <li>{{ $nota }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($fila['programacion_fisica'] ?? 0), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($fila['avance_fisico'] ?? 0), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">${{ number_format((float) ($fila['comprometido'] ?? 0), 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    @if(($fila['estado'] ?? '') === 'valida')
                                        <span class="status status-approved">Válida</span>
                                    @else
                                        <span class="status status-error">Bloqueada</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">Todavía no hay diagnóstico.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($cargas->count() > 1)
            <section class="panel">
                <h2 class="text-lg font-semibold">Cargas anteriores</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($cargas as $item)
                        <a class="btn-small" href="{{ route('intelligence.reporte-mensual.historicas.index', [$seguimiento, 'carga' => $item->id]) }}">
                            {{ $item->id }} · {{ $item->status }} · {{ $item->created_at->format('d/m H:i') }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @else
        <section class="panel">
            <p class="text-sm text-slate-500">Aún no hay cargas históricas diagnosticadas para este seguimiento.</p>
        </section>
    @endif
</div>
@endsection
