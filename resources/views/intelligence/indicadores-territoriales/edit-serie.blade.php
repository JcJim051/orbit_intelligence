<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a class="text-sm font-bold text-indigo-700 hover:underline" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">← Volver a la ficha del indicador</a>
        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">{{ $indicador->datos->count() }} periodo(s)</span>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><strong>No fue posible guardar la serie.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-950 to-indigo-800 p-6 text-white">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-200">{{ $indicador->codigo }} · Serie histórica</p>
            <h1 class="mt-2 text-2xl font-black">Editar serie de datos</h1>
            <p class="mt-2 max-w-4xl text-sm text-indigo-100">{{ $indicador->nombre }}</p>
        </div>

        @if($indicador->datos->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">Este indicador todavía no tiene registros. Cárguelos desde la plantilla histórica en la ficha principal.</div>
        @else
            <form method="post" action="{{ route('intelligence.indicadores-territoriales.historico.update', $indicador) }}" class="space-y-5 p-6">
                @csrf @method('PATCH')
                <div class="space-y-4">
                    @foreach($indicador->datos as $dato)
                        @php($field = fn (string $name, mixed $default = null) => old("datos.{$dato->id}.{$name}", $default))
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                                <div><span class="text-xs font-bold uppercase tracking-wider text-slate-500">Periodo</span><h2 class="text-lg font-black text-slate-950">{{ $dato->periodo }}</h2></div>
                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $dato->estado === 'aprobado' ? 'bg-emerald-100 text-emerald-800' : ($dato->estado === 'observado' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">{{ \App\Models\IndicadorTerritorialDato::ESTADOS[$dato->estado] ?? $dato->estado }}</span>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <label><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Valor *</span><input class="w-full rounded-xl border-slate-300 bg-white" type="number" step="any" name="datos[{{ $dato->id }}][valor]" value="{{ $field('valor', $dato->valor) }}" required></label>
                                <label><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Fecha inicial</span><input class="w-full rounded-xl border-slate-300 bg-white" type="date" name="datos[{{ $dato->id }}][periodo_inicio]" value="{{ $field('periodo_inicio', $dato->periodo_inicio?->format('Y-m-d')) }}"></label>
                                <label><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Fecha final</span><input class="w-full rounded-xl border-slate-300 bg-white" type="date" name="datos[{{ $dato->id }}][periodo_fin]" value="{{ $field('periodo_fin', $dato->periodo_fin?->format('Y-m-d')) }}"></label>
                                <label><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Fecha de corte</span><input class="w-full rounded-xl border-slate-300 bg-white" type="date" name="datos[{{ $dato->id }}][fecha_corte]" value="{{ $field('fecha_corte', $dato->fecha_corte?->format('Y-m-d')) }}"></label>
                                <label class="xl:col-span-2"><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Fuente</span><input class="w-full rounded-xl border-slate-300 bg-white" name="datos[{{ $dato->id }}][fuente]" value="{{ $field('fuente', $dato->fuente) }}"></label>
                                <label class="xl:col-span-2"><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Enlace de evidencia</span><input class="w-full rounded-xl border-slate-300 bg-white" type="url" name="datos[{{ $dato->id }}][enlace_evidencia]" value="{{ $field('enlace_evidencia', $dato->enlace_evidencia) }}" placeholder="https://..."></label>
                                <label class="md:col-span-2 xl:col-span-4"><span class="mb-1 block text-xs font-bold uppercase text-slate-600">Observación</span><textarea class="w-full rounded-xl border-slate-300 bg-white" rows="2" name="datos[{{ $dato->id }}][observacion]">{{ $field('observacion', $dato->observacion) }}</textarea></label>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <label><span class="block text-sm font-black text-amber-950">Justificación de la corrección</span><textarea class="mt-2 w-full rounded-xl border-amber-300 bg-white" rows="3" name="justificacion" placeholder="Obligatoria cuando se modifica un dato aprobado.">{{ old('justificacion') }}</textarea></label>
                    <p class="mt-2 text-xs text-amber-800">Todo registro modificado regresará a pendiente de validación. El valor anterior y el responsable del cambio quedarán registrados para auditoría.</p>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-5"><a class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700" href="{{ route('intelligence.indicadores-territoriales.show', $indicador) }}">Cancelar</a><button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Guardar cambios</button></div>
            </form>
        @endif
    </section>
</div>
