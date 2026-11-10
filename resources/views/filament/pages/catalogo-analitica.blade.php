<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-sm text-gray-600">
            Vistas de solo lectura del esquema analitica. Los códigos van como código — nombre.
            Crear un tablero no publica datos nuevos: Superset lee estas vistas.
        </p>
        @if ($superset)
            <a href="{{ $superset }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">
                Crear tablero en Superset
            </a>
        @else
            <p class="text-sm text-amber-700">Defina SUPERSET_URL para abrir el constructor.</p>
        @endif
    </div>

    <div class="mt-6 grid gap-4">
        @foreach ($vistas as $vista)
            <article class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">{{ $vista['dominio'] }}</p>
                        <h2 class="text-lg font-semibold">{{ $vista['nombre'] }}</h2>
                    </div>
                    <div class="flex gap-6 text-center">
                        <div>
                            <p class="text-xs text-gray-500">Columnas</p>
                            @if ($vista['columnas'] > 0)
                                <button type="button" wire:click="abrirColumnas(@js($vista['nombre']))" class="text-2xl font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4">
                                    {{ $vista['columnas'] }}
                                </button>
                            @else
                                <p class="text-2xl font-semibold text-gray-400">0</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Filas</p>
                            @if (($vista['filas'] ?? 0) > 0)
                                <button type="button" wire:click="abrirFilas(@js($vista['nombre']), {{ (int) $vista['filas'] }})" class="text-2xl font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4">
                                    {{ number_format((int) $vista['filas'], 0, ',', '.') }}
                                </button>
                            @else
                                <p class="text-2xl font-semibold text-gray-400">{{ $vista['filas'] === null ? '—' : '0' }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                <p class="mt-2 text-sm text-gray-700">{{ $vista['descripcion'] }}</p>
                <p class="mt-2 text-xs text-gray-500">Fuente: {{ $vista['fuente'] }}</p>
                <p class="text-xs text-gray-500">Corte: {{ $vista['corte'] }}</p>
            </article>
        @endforeach
    </div>

    <section class="mt-8 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h2 class="font-semibold text-amber-900">Todavía no están en el SIID</h2>
        <ul class="mt-2 space-y-2 text-sm text-amber-950">
            @foreach ($pendientes as $pendiente)
                <li><strong>{{ $pendiente['nombre'] }}.</strong> {{ $pendiente['descripcion'] }}</li>
            @endforeach
        </ul>
    </section>
</x-filament-panels::page>
