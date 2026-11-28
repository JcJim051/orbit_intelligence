<x-filament-panels::page>
    <div class="max-w-4xl space-y-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Seguimiento a metas</p>
            <h2 class="mt-1 text-xl font-black text-slate-950">Integración de indicadores territoriales</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Este interruptor controla si los indicadores forman parte del diligenciamiento, envío y cierre del reporte mensual de metas. No modifica fichas técnicas, históricos ni reportes existentes.
            </p>

            <form wire:submit="save" class="mt-6 space-y-5">
                <label class="flex cursor-pointer items-start justify-between gap-5 rounded-2xl border p-5 {{ $reporteIndicadoresActivo ? 'border-emerald-300 bg-emerald-50' : 'border-amber-300 bg-amber-50' }}">
                    <span>
                        <strong class="block text-base text-slate-950">Incluir indicadores territoriales en el reporte de metas</strong>
                        <span class="mt-1 block text-sm leading-6 text-slate-600">
                            {{ $reporteIndicadoresActivo
                                ? 'Activo: los indicadores se muestran y son obligatorios según su cronograma.'
                                : 'Apagado: el flujo mensual valida y cierra únicamente los proyectos y sus metas.' }}
                        </span>
                    </span>
                    <input wire:model.live="reporteIndicadoresActivo" type="checkbox" class="mt-1 h-6 w-11 rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                </label>

                <label class="flex cursor-pointer items-start justify-between gap-5 rounded-2xl border p-5 {{ $edicionValidadoresInformacionActiva ? 'border-emerald-300 bg-emerald-50' : 'border-slate-300 bg-slate-50' }}">
                    <span>
                        <strong class="block text-base text-slate-950">Permitir edición a validadores de información</strong>
                        <span class="mt-1 block text-sm leading-6 text-slate-600">
                            {{ $edicionValidadoresInformacionActiva
                                ? 'Activo: los validadores asignados pueden preparar fichas y corregir datos que aún no estén aprobados.'
                                : 'Bloqueado: los validadores conservan consulta y aprobación, pero no pueden modificar fichas, importar, crear, editar ni borrar datos.' }}
                        </span>
                    </span>
                    <input wire:model.live="edicionValidadoresInformacionActiva" type="checkbox" class="mt-1 h-6 w-11 rounded-full border-slate-300 text-emerald-600 focus:ring-emerald-500">
                </label>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        @if($actualizadoAt)
                            Último cambio: {{ $actualizadoAt }}{{ $actualizadoPor ? ' · '.$actualizadoPor : '' }}
                        @else
                            Configuración inicial activa.
                        @endif
                    </p>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 disabled:opacity-60">
                        Guardar configuración
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
            <strong>Importante:</strong> los cortes que se cierren mientras el proceso esté apagado no se recalcularán al volver a activarlo. Los cortes que permanezcan abiertos recuperarán inmediatamente las obligaciones de indicadores.
        </section>
    </div>
</x-filament-panels::page>
