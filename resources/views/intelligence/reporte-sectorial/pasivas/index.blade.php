@extends('layouts.app', ['title' => 'Líneas de pasiva · '.$seguimiento->etiqueta().' · SIID 2.0'])

@php($pesos = fn ($valor) => App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial::pesos($valor))

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Reporte mensual de metas</p>
            <h1 class="page-title">Líneas de pasiva · {{ $seguimiento->etiqueta() }}</h1>
            <p class="page-subtitle max-w-3xl">Filas hoja de la pasiva vigente. Cada línea se asigna a una dependencia con las reglas de pasiva y a un BPIN; las que no se resuelven quedan pendientes de revisión. Solo lectura.</p>
        </div>
        <a class="btn-secondary" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Volver al seguimiento</a>
    </header>

    <section class="panel">
        <form method="get" class="grid gap-4 md:grid-cols-4">
            <label class="field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach(App\Enums\EstadoRevisionPasiva::cases() as $estado)
                        <option value="{{ $estado->value }}" @selected($filtros['estado'] === $estado->value)>{{ $estado->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field md:col-span-2"><span>Buscar</span><input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="BPIN, rubro o nombre del proyecto"></label>
            <div class="flex items-end gap-2"><button class="btn-primary">Filtrar</button></div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-semibold">Líneas</h2>
            <p class="text-sm text-slate-500">{{ $lineas->total() }} en total</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Fila</th>
                        <th class="px-4 py-3">Identificación — concepto</th>
                        <th class="px-4 py-3">Proyecto</th>
                        <th class="px-4 py-3">Fuente</th>
                        <th class="px-4 py-3">Dependencia</th>
                        <th class="px-4 py-3 text-right">Definitiva</th>
                        <th class="px-4 py-3 text-right">Compromisos</th>
                        <th class="px-4 py-3 text-right">Pagos</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($lineas as $linea)
                        <tr>
                            <td class="px-4 py-3 align-top">{{ $linea->fila }}</td>
                            <td class="max-w-sm px-4 py-3 align-top">{{ $linea->identificacion_presupuestal }} — {{ $linea->concepto }}</td>
                            <td class="max-w-sm px-4 py-3 align-top">{{ $linea->proyecto?->etiqueta() ?? ($linea->bpin ?? '—') }}</td>
                            <td class="px-4 py-3 align-top">{{ $linea->fuente?->etiqueta() ?? $linea->codigo_fuente }}</td>
                            <td class="px-4 py-3 align-top">{{ $linea->dependencia?->etiqueta() ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $pesos($linea->apropiacion_definitiva) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $pesos($linea->compromisos) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">{{ $pesos($linea->pagos) }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="status {{ $linea->estado_revision === App\Enums\EstadoRevisionPasiva::Pendiente ? 'status-error' : '' }}">{{ $linea->estado_revision->label() }}</span>
                                @if($linea->motivos_revision)
                                    <p class="mt-1 text-xs text-slate-500">{{ collect($linea->motivos_revision)->map(fn ($m) => str_replace('_', ' ', $m))->implode(', ') }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">No hay líneas con esos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lineas->hasPages())
            <div class="border-t border-slate-100 px-5 py-4 text-sm">{{ $lineas->links() }}</div>
        @endif
    </section>
</div>
@endsection
