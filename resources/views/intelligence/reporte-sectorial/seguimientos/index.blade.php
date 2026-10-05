@extends('layouts.app', ['title' => 'Reporte mensual de metas · SIID 2.0'])

@section('content')
<div class="space-y-6">
    <header>
        <p class="eyebrow">In-Orbit Intelligence</p>
        <h1 class="page-title">Reporte mensual de metas</h1>
        <p class="page-subtitle max-w-3xl">Cada seguimiento es un corte mensual. La Gerencia lo crea y le adjunta la pasiva del PCT; de ella salen los techos por BPIN, fuente y dependencia. Cada sector ve y reporta solo sus proyectos.</p>
    </header>

    @can('create', App\Models\Seguimiento::class)
        <section class="panel">
            <h2 class="text-lg font-semibold">Nuevo seguimiento</h2>
            <form method="post" action="{{ route('intelligence.reporte-mensual.store') }}" class="mt-4 grid gap-4 md:grid-cols-5">
                @csrf
                <label class="field">
                    <span>Vigencia</span>
                    <input type="number" name="vigencia" value="{{ old('vigencia', now()->year) }}" min="2024" max="2035" required>
                </label>
                <label class="field">
                    <span>Mes de corte</span>
                    <select name="mes" required>
                        @foreach([1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $numero => $nombre)
                            <option value="{{ $numero }}" @selected((int) old('mes', now()->subMonth()->month) === $numero)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Observación</span>
                    <input type="text" name="observacion" value="{{ old('observacion') }}" maxlength="2000">
                </label>
                <label class="field">
                    <span>Modo de captura</span>
                    <select name="modo_captura" required>
                        @foreach(App\Models\Seguimiento::modosCaptura() as $valor => $label)
                            <option value="{{ $valor }}" @selected(old('modo_captura', App\Models\Seguimiento::MODO_OPERATIVO) === $valor)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end"><button class="btn-primary">Crear seguimiento</button></div>
            </form>
        </section>
    @endcan

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-lg font-semibold">Seguimientos</h2></div>
        <table data-siid-datatable class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Corte</th>
                    <th class="px-4 py-3">Fecha de corte</th>
                    <th class="px-4 py-3">Modo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Pasiva vigente</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($seguimientos as $seguimiento)
                    <tr>
                        <td class="px-4 py-3 font-semibold">{{ $seguimiento->etiqueta() }}</td>
                        <td class="px-4 py-3">{{ $seguimiento->fecha_corte->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $seguimiento->modoCapturaLabel() }}</td>
                        <td class="px-4 py-3"><span class="status {{ $seguimiento->estaCerrado() ? 'status-approved' : 'status-pending_review' }}">{{ $seguimiento->estado->label() }}</span></td>
                        <td class="px-4 py-3">{{ $seguimiento->pasivaVigente?->nombre_original ?? 'Sin pasiva' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-3">
                                <a class="font-semibold text-indigo-700" href="{{ route('intelligence.reporte-mensual.show', $seguimiento) }}">Ver avance</a>
                                <a class="font-semibold text-slate-600" href="{{ route('intelligence.reporte-mensual.datos-base', $seguimiento) }}">Datos base</a>
                                @can('update', $seguimiento)
                                    <a class="font-semibold text-emerald-700" href="{{ route('intelligence.reporte-mensual.edit', $seguimiento) }}">Editar</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Aún no hay seguimientos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
