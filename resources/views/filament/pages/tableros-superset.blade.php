<x-filament-panels::page>
    <p class="text-sm text-gray-600">
        El navegador recibe un token de invitado de corta duración. El rol configurado en Superset es <strong>{{ $rol }}</strong>.
        La restricción por sector sale de las dependencias del usuario, no de un parámetro libre.
    </p>

    @if (! $configurado)
        <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">Superset no está configurado. Defina SUPERSET_URL y la cuenta de servicio.</p>
    @elseif ($indicadores->isEmpty())
        <p class="mt-4 text-sm text-gray-600">Ningún indicador tiene UUID de tablero.</p>
    @else
        <form method="get" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm">
                Indicador
                <select name="indicador" class="mt-1 block rounded-lg border-gray-300">
                    @foreach ($indicadores as $indicador)
                        <option value="{{ $indicador->codigo }}" @selected($seleccionado?->codigo === $indicador->codigo)>{{ $indicador->etiqueta() }}</option>
                    @endforeach
                </select>
            </label>
            @if ($dependencias->isNotEmpty())
                <label class="text-sm">
                    Sector
                    <select name="dependencia_id" class="mt-1 block rounded-lg border-gray-300">
                        <option value="">{{ auth()->user()?->veTodosLosSectores() ? 'Todos los sectores' : 'Mis sectores' }}</option>
                        @foreach ($dependencias as $dependencia)
                            <option value="{{ $dependencia->id }}" @selected($dependenciaId === $dependencia->id)>{{ $dependencia->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Ver tablero</button>
        </form>

        @if ($seleccionado)
            <div
                class="mt-4 h-[1100px] overflow-hidden rounded-xl border border-gray-200 bg-white"
                data-superset
                data-token-url="{{ route('micrositio.tableros.token', ['codigo' => $seleccionado->codigo, 'dependencia_id' => $dependenciaId]) }}"
                data-dominio="{{ config('superset.url') }}"
            ></div>
        @endif
    @endif
</x-filament-panels::page>
