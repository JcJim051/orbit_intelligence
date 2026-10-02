@extends('layouts.app', ['title' => $proyecto->bpin.' · Reporte mensual · SIID 2.0'])

@php($pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor))
@php($rutaBase = [$seguimiento, $proyecto, 'dependencia' => $reporte->dependencia_id])
@php($totalTecho = (float) $techos->sum(fn ($techo) => (float) $techo->valor))
@php($totalReportadoFuente = (float) $techos->sum(fn ($techo) => (float) ($reportadoPorFuente[$techo->fuente_financiacion_id] ?? 0)))
@php($totalSaldo = $totalTecho - $totalReportadoFuente)
@php($totalProgramadoFinanciero = (float) $actividades->sum(fn ($actividad) => (float) $actividad->programaciones->whereIn('fuente_financiacion_id', $techos->pluck('fuente_financiacion_id'))->sum('valor_asignado')))
@php($totalComprometido = (float) $ejecuciones->sum('comprometido'))
@php($totalObligado = (float) $ejecuciones->sum('obligado'))
@php($totalPagado = (float) $ejecuciones->sum('pagado'))

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Reporte mensual · {{ $seguimiento->etiqueta() }}</p>
            <h1 class="page-title">{{ $proyecto->etiqueta() }}</h1>
            <p class="page-subtitle max-w-3xl">
                {{ $reporte->dependencia->etiqueta() }} ·
                <span class="status {{ $reporte->estado->cssClass() }}">{{ $reporte->estado->label() }}</span>
                · Focalización: {{ $proyecto->tipo_focalizacion?->label() ?? 'por definir' }}
                @if($proyecto->tipo_focalizacion === App\Enums\TipoFocalizacion::Municipal && $proyecto->municipio) ({{ $proyecto->municipio->codigo_dane }} — {{ $proyecto->municipio->nombre }}) @endif
            </p>
            @if($reporte->observacion_revision)
                <p class="mt-2 text-sm text-slate-600"><strong>Observación de la Gerencia:</strong> {{ $reporte->observacion_revision }}</p>
            @endif
        </div>
        <div class="flex flex-wrap items-end gap-2">
            @if($dependencias->count() > 1)
                <form method="get" class="flex items-end gap-2">
                    <label class="field"><span>Dependencia</span>
                        <select name="dependencia" onchange="this.form.submit()">
                            @foreach($dependencias as $dependencia)
                                <option value="{{ $dependencia->id }}" @selected($dependencia->id === $reporte->dependencia_id)>{{ $dependencia->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            @endif
            <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Volver</a>
            @if($puedeEditar)
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.enviar', $rutaBase) }}">
                    @csrf
                    <button class="btn-primary" @disabled($pendientes['mensajes'] !== [])>Enviar reporte</button>
                </form>
            @endif
        </div>
    </header>

    @if($pendientes['mensajes'] !== [] && $puedeEditar)
        <section class="panel border-amber-200 bg-amber-50">
            <h2 class="text-base font-semibold text-amber-900">Pendientes para enviar</h2>
            <ul class="mt-2 list-disc pl-5 text-sm text-amber-900">
                @foreach($pendientes['mensajes'] as $mensaje)<li>{{ $mensaje }}</li>@endforeach
            </ul>
        </section>
    @endif

    @if($puedeRevisar)
        <section class="panel">
            <h2 class="text-lg font-semibold">Revisión de la Gerencia</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.aprobar', $rutaBase) }}" class="space-y-2">
                    @csrf
                    <label class="field"><span>Observación (opcional)</span><textarea name="observacion" rows="2"></textarea></label>
                    <button class="btn-primary">Aprobar</button>
                </form>
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.devolver', $rutaBase) }}" class="space-y-2">
                    @csrf
                    <label class="field"><span>Motivo de la devolución</span><textarea name="observacion" rows="2" required></textarea></label>
                    <button class="btn-danger">Devolver al sector</button>
                </form>
            </div>
        </section>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Techos por fuente</h2>
            <p class="text-sm text-slate-500">Calculados desde la pasiva del PCT. Lo comprometido por fuente no puede superar el techo.</p>
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fuente</th>
                    <th class="px-4 py-3">Grupo</th>
                    <th class="px-4 py-3 text-right">Techo</th>
                    <th class="px-4 py-3 text-right">Reportado</th>
                    <th class="px-4 py-3 text-right">Saldo</th>
                    <th class="px-4 py-3">Historial</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($techos as $techo)
                    @php($reportado = (float) ($reportadoPorFuente[$techo->fuente_financiacion_id] ?? 0))
                    <tr>
                        <td class="px-4 py-3">{{ $techo->fuente->etiqueta() }} @if($techo->valor_ajuste !== null)<span class="status status-pending_review">Ajustado</span>@endif</td>
                        <td class="px-4 py-3">{{ $techo->fuente->grupo()->label() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($techo->valor) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($reportado) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos((float) $techo->valor - $reportado) }}</td>
                        <td class="px-4 py-3">
                            <x-reporte-sectorial.conteo :conteo="$techo->historial->count()" :url="route('intelligence.reporte-mensual.proyectos.detalle', $rutaBase + ['relacion' => 'historial_techos'])" titulo="Ver historial del techo" />
                            @can('ajustar', $techo)
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-semibold text-indigo-700">Ajuste excepcional</summary>
                                    <form method="post" action="{{ route('intelligence.reporte-mensual.techos.ajustar', $techo) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                                        @csrf
                                        <label class="field"><span>Nuevo techo</span><input type="number" step="0.01" min="0" name="valor" required></label>
                                        <label class="field"><span>Motivo</span><textarea name="motivo" rows="2" required minlength="10"></textarea></label>
                                        <label class="field"><span>Soporte</span><input type="file" name="soporte" required></label>
                                        <button class="btn-small">Guardar ajuste</button>
                                    </form>
                                </details>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-slate-200 bg-slate-50 text-sm font-bold text-slate-900">
                <tr>
                    <td class="px-4 py-3" colspan="2">Total proyecto</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($totalTecho) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">{{ $pesos($totalReportadoFuente) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right {{ $totalSaldo < 0 ? 'text-red-700' : '' }}">{{ $pesos($totalSaldo) }}</td>
                    <td class="px-4 py-3"></td>
                </tr>
            </tfoot>
        </table>
    </section>

    <section class="panel space-y-4">
        <div class="panel-head"><h2>Ejecución financiera por actividad</h2><span>Cada actividad inicia cerrada. Puede guardar avances parciales antes de enviar.</span></div>
        @if($actividades->isEmpty())
            <p class="text-sm text-slate-500">Registre primero las actividades del proyecto.</p>
        @else
            <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.ejecucion.update', $rutaBase) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="space-y-3">
                    @php($fila = 0)
                    @foreach($actividades as $actividad)
                        @php($avance = $avances->get($actividad->id))
                        @php($programadoActividad = (float) $actividad->programaciones->whereIn('fuente_financiacion_id', $techos->pluck('fuente_financiacion_id'))->sum('valor_asignado'))
                        @php($comprometidoActividad = (float) $techos->sum(fn ($techo) => (float) ($ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id)?->comprometido ?? 0)))
                        @php($obligadoActividad = (float) $techos->sum(fn ($techo) => (float) ($ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id)?->obligado ?? 0)))
                        @php($pagadoActividad = (float) $techos->sum(fn ($techo) => (float) ($ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id)?->pagado ?? 0)))
                        <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <summary class="flex cursor-pointer list-none flex-col gap-3 bg-slate-50 px-4 py-3 marker:hidden md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h3 class="font-semibold text-slate-900">{{ $actividad->etiqueta() }}</h3>
                                    <p class="text-xs text-slate-500">
                                        Programado: {{ $pesos($programadoActividad) }} · Comprometido: {{ $pesos($comprometidoActividad) }} · Pagado: {{ $pesos($pagadoActividad) }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                        Meta física: {{ $avance ? rtrim(rtrim(number_format((float) $avance->cantidad, 4, ',', '.'), '0'), ',') : 'sin reportar' }}
                                    </span>
                                    @if($puedeEditar)
                                        <button type="button" class="btn-small" data-physical-modal-target="meta-fisica-{{ $actividad->id }}">Reportar meta física</button>
                                    @endif
                                </div>
                            </summary>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-white text-left text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2">Fuente</th>
                                            <th class="px-3 py-2 text-right">Programado</th>
                                            <th class="px-3 py-2">Comprometido</th>
                                            <th class="px-3 py-2">Obligado</th>
                                            <th class="px-3 py-2">Pagado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($techos as $techo)
                                            @php($ejecucion = $ejecuciones->get($actividad->id.'-'.$techo->fuente_financiacion_id))
                                            @php($programado = $actividad->programaciones->firstWhere('fuente_financiacion_id', $techo->fuente_financiacion_id))
                                            @php($programadoValor = $programado?->valor_asignado !== null ? (float) $programado->valor_asignado : (float) $techo->valor)
                                            @php($usaTechoComoReferencia = $programado?->valor_asignado === null && (float) $techo->valor > 0)
                                            <tr>
                                                <td class="px-3 py-2 align-top">
                                                    {{ $techo->fuente->etiqueta() }}
                                                    <input type="hidden" name="ejecucion[{{ $fila }}][actividad_id]" value="{{ $actividad->id }}">
                                                    <input type="hidden" name="ejecucion[{{ $fila }}][fuente_financiacion_id]" value="{{ $techo->fuente_financiacion_id }}">
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-right align-top">
                                                    {{ $pesos($programadoValor) }}
                                                    @if($usaTechoComoReferencia)
                                                        <span class="block text-[11px] font-medium text-amber-700">techo sin distribuir</span>
                                                    @endif
                                                </td>
                                                @foreach(['comprometido', 'obligado', 'pagado'] as $campo)
                                                    <td class="px-3 py-2"><input class="w-36 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.01" min="0" name="ejecucion[{{ $fila }}][{{ $campo }}]" value="{{ old("ejecucion.$fila.$campo", $ejecucion?->{$campo} ?? 0) }}" @disabled(! $puedeEditar)></td>
                                                @endforeach
                                            </tr>
                                            @php($fila++)
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t border-slate-200 bg-slate-50 text-sm font-bold text-slate-900">
                                        <tr>
                                            <td class="px-3 py-3">Total actividad</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $pesos($programadoActividad) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $pesos($comprometidoActividad) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $pesos($obligadoActividad) }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ $pesos($pagadoActividad) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </details>
                    @endforeach
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><span class="block text-xs uppercase tracking-wide text-slate-500">Programado distribuido</span><strong>{{ $pesos($totalProgramadoFinanciero) }}</strong></div>
                        <div><span class="block text-xs uppercase tracking-wide text-slate-500">Comprometido</span><strong>{{ $pesos($totalComprometido) }}</strong></div>
                        <div><span class="block text-xs uppercase tracking-wide text-slate-500">Obligado</span><strong>{{ $pesos($totalObligado) }}</strong></div>
                        <div><span class="block text-xs uppercase tracking-wide text-slate-500">Pagado</span><strong>{{ $pesos($totalPagado) }}</strong></div>
                    </div>
                </div>
                @if($puedeEditar)<button class="btn-primary">Guardar ejecución</button>@endif
            </form>
        @endif
    </section>

    @foreach($actividades as $actividad)
        @php($avance = $avances->get($actividad->id))
        <dialog id="meta-fisica-{{ $actividad->id }}" class="w-full max-w-3xl rounded-3xl border border-slate-200 p-0 shadow-2xl backdrop:bg-slate-950/50">
            <div class="border-b border-slate-100 px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="eyebrow">Reporte de meta física</p>
                        <h2 class="text-xl font-bold text-slate-950">{{ $actividad->etiqueta() }}</h2>
                        <p class="text-sm text-slate-500">Programado: {{ $actividad->cantidad_programada !== null ? rtrim(rtrim(number_format((float) $actividad->cantidad_programada, 4, ',', '.'), '0'), ',') : '—' }} {{ $actividad->unidad_medida }}</p>
                    </div>
                    <button type="button" class="btn-small" data-physical-modal-close>Cerrar</button>
                </div>
            </div>
            <div class="space-y-4 px-6 py-5">
                @if($avance && $avance->evidencias->isNotEmpty())
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach($avance->evidencias as $evidencia)
                            <li class="flex items-center justify-between gap-2">
                                <a class="text-indigo-700 underline" href="{{ route('intelligence.reporte-mensual.evidencias.show', $evidencia) }}">{{ $evidencia->nombre_original }}</a>
                                <span class="text-xs text-slate-500">{{ number_format($evidencia->bytes / 1024, 0, ',', '.') }} KB · SHA-256 {{ substr($evidencia->sha256, 0, 12) }}… · {{ $evidencia->subidoPor?->name }} · {{ $evidencia->created_at->format('d/m/Y H:i') }}</span>
                                @can('delete', $evidencia)
                                    <form method="post" action="{{ route('intelligence.reporte-mensual.evidencias.destroy', $evidencia) }}">@csrf @method('DELETE')<button class="btn-small">Quitar</button></form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @elseif($avance && (float) $avance->cantidad > 0)
                    <p class="mt-2 text-sm text-red-700">Falta la evidencia de este avance.</p>
                @endif
                <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.avances.store', [$seguimiento, $proyecto, $actividad, 'dependencia' => $reporte->dependencia_id]) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 md:grid-cols-4">
                    @csrf
                    <label class="field"><span>Cantidad del periodo</span><input type="number" step="0.0001" min="0" name="cantidad" value="{{ $avance ? (float) $avance->cantidad : 0 }}" @disabled(! $puedeEditar) required></label>
                    <label class="field"><span>Fecha de ejecución</span><input type="date" name="fecha_ejecucion" value="{{ $avance?->fecha_ejecucion?->toDateString() }}" max="{{ $seguimiento->fecha_corte->toDateString() }}" @disabled(! $puedeEditar)></label>
                    <label class="field md:col-span-2"><span>Descripción</span><input type="text" name="descripcion" value="{{ $avance?->descripcion }}" @disabled(! $puedeEditar)></label>
                    @if($puedeEditar)
                        <label class="field md:col-span-2"><span>Evidencias</span><input type="file" name="evidencias[]" multiple></label>
                        <label class="field"><span>Descripción de la evidencia</span><input type="text" name="descripcion_evidencia"></label>
                        <div class="flex items-end"><button class="btn-secondary">Guardar avance</button></div>
                    @endif
                </form>
            </div>
        </dialog>
    @endforeach

    @if($puedeEditar)
        <section class="panel">
            <h2 class="text-lg font-semibold">Nueva actividad</h2>
            <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.actividades.store', $rutaBase) }}" class="mt-4 grid gap-4 md:grid-cols-4">
                @csrf
                <label class="field"><span>Código</span><input type="text" name="codigo" maxlength="40"></label>
                <label class="field md:col-span-3"><span>Nombre</span><input type="text" name="nombre" required></label>
                <label class="field"><span>Unidad de medida</span><input type="text" name="unidad_medida"></label>
                <label class="field"><span>Cantidad programada</span><input type="number" step="0.0001" min="0" name="cantidad_programada"></label>
                @foreach($techos as $indice => $techo)
                    <label class="field">
                        <span>Programado {{ $techo->fuente->etiqueta() }}</span>
                        <input type="hidden" name="programacion[{{ $indice }}][fuente_financiacion_id]" value="{{ $techo->fuente_financiacion_id }}">
                        <input type="number" step="0.01" min="0" name="programacion[{{ $indice }}][valor_asignado]" value="0">
                    </label>
                @endforeach
                <div class="flex items-end"><button class="btn-primary">Agregar actividad</button></div>
            </form>
        </section>
    @endif

    @if($proyecto->requiereFocalizacionMensual())
        <section class="panel space-y-4">
            <div class="panel-head"><h2>Focalización por municipio</h2><span>Debe sumar 100 %. Se precarga el corte anterior; si cambia, justifique.</span></div>
            <form method="post" action="{{ route('intelligence.reporte-mensual.proyectos.focalizacion.update', $rutaBase) }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="max-h-[60vh] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="px-3 py-2">Municipio</th><th class="px-3 py-2">% </th><th class="px-3 py-2">Valor</th><th class="px-3 py-2">Cantidad</th><th class="px-3 py-2">Corte anterior</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($municipios as $indice => $municipio)
                                @php($actual = $focalizacionActual->get($municipio->id))
                                @php($previa = $focalizacionPrevia[$municipio->id] ?? null)
                                <tr>
                                    <td class="px-3 py-2">{{ $municipio->codigo_dane }} — {{ $municipio->nombre }}<input type="hidden" name="focalizacion[{{ $indice }}][municipio_id]" value="{{ $municipio->id }}"></td>
                                    <td class="px-3 py-2"><input class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.0001" min="0" max="100" name="focalizacion[{{ $indice }}][porcentaje]" value="{{ $actual ? (float) $actual->porcentaje : ($previa['porcentaje'] ?? '') }}" @disabled(! $puedeEditar)></td>
                                    <td class="px-3 py-2"><input class="w-36 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.01" min="0" name="focalizacion[{{ $indice }}][valor]" value="{{ $actual?->valor !== null ? (float) $actual->valor : '' }}" @disabled(! $puedeEditar)></td>
                                    <td class="px-3 py-2"><input class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right" type="number" step="0.0001" min="0" name="focalizacion[{{ $indice }}][cantidad]" value="{{ $actual?->cantidad !== null ? (float) $actual->cantidad : '' }}" @disabled(! $puedeEditar)></td>
                                    <td class="px-3 py-2 text-slate-500">{{ $previa ? $previa['porcentaje'].' %' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 text-sm font-bold text-slate-900">
                            <tr>
                                <td class="px-3 py-3">Total focalización</td>
                                <td class="px-3 py-3 text-right">{{ number_format((float) $focalizacionActual->sum('porcentaje'), 4, ',', '.') }} %</td>
                                <td class="px-3 py-3 text-right">{{ $pesos((float) $focalizacionActual->sum('valor')) }}</td>
                                <td class="px-3 py-3 text-right">{{ rtrim(rtrim(number_format((float) $focalizacionActual->sum('cantidad'), 4, ',', '.'), '0'), ',') }}</td>
                                <td class="px-3 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <label class="field"><span>Justificación del cambio frente al corte anterior</span><textarea name="justificacion_focalizacion" rows="2" @disabled(! $puedeEditar)>{{ $reporte->justificacion_focalizacion }}</textarea></label>
                @if($puedeEditar)<button class="btn-primary">Guardar focalización</button>@endif
            </form>
        </section>
    @endif
</div>

<script>
    document.querySelectorAll('[data-physical-modal-target]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            document.getElementById(button.dataset.physicalModalTarget)?.showModal();
        });
    });

    document.querySelectorAll('[data-physical-modal-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
</script>

@include('intelligence.catalogs.partials.count-detail-dialog', ['catalog' => new class { public function title(): string { return 'Reporte mensual de metas'; } }])
@endsection
