<x-filament-panels::page>
    <p class="text-sm text-gray-600">
        La pasiva vigente del reporte mensual se sincroniza sola a este esquema. También puede cargar un archivo aquí. Cada corte queda en el histórico; el tablero lee la vista del corte más reciente.
    </p>

    @if (session('status'))
        <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form method="post" action="{{ route('analitica.pasiva.store') }}" enctype="multipart/form-data" class="mt-4 max-w-xl space-y-3 rounded-xl border border-gray-200 bg-white p-4">
        @csrf
        <label class="block text-sm font-medium">Archivo .xlsx o .csv
            <input type="file" name="archivo" accept=".xlsx,.csv,.txt" required class="mt-1 block w-full text-sm">
        </label>
        <label class="block text-sm font-medium">Corte
            <input type="date" name="corte" class="mt-1 block rounded-lg border-gray-300">
        </label>
        <p class="text-xs text-gray-500">Si el archivo trae la fecha en el encabezado, el corte se toma de ahí cuando este campo queda vacío.</p>
        @error('archivo') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @error('corte') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        <button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">Cargar pasiva</button>
    </form>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        @forelse ($grupos as $grupo)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-sm text-gray-500">{{ $grupo->grupo_fuente }}</p>
                @if ((int) $grupo->rubros > 0)
                    <button type="button" wire:click="abrirDetalle(@js($grupo->grupo_fuente), {{ (int) $grupo->rubros }})" class="mt-1 text-3xl font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4">
                        {{ number_format((int) $grupo->rubros, 0, ',', '.') }}
                    </button>
                @else
                    <p class="mt-1 text-3xl font-semibold text-gray-400">0</p>
                @endif
                <p class="mt-2 text-xs text-gray-500">rubros · definitivo {{ number_format((float) $grupo->definitiva, 0, ',', '.') }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-600">Aún no hay una pasiva cargada en el esquema analitica.</p>
        @endforelse
    </div>
    @if ($vigente)
        <p class="mt-4 text-sm text-gray-700">
            Corte vigente: <strong>{{ \Illuminate\Support\Carbon::parse($vigente->fecha_corte)->format('d/m/Y') }}</strong>.
            Cargado: {{ \Illuminate\Support\Carbon::parse($vigente->fecha_carga)->format('d/m/Y H:i') }}.
            Fuente: {{ $vigente->origen }}
        </p>
    @endif

    <h2 class="mt-8 text-base font-semibold">Registro de sincronización</h2>
    <div class="mt-3 overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-gray-500">
                    <th class="px-3 py-2">Corte</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Rubros</th>
                    <th class="px-3 py-2">Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sincronizaciones as $registro)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $registro->corte?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-2 {{ $registro->estado === 'error' ? 'font-medium text-red-700' : 'text-gray-700' }}">{{ $registro->estado }}</td>
                        <td class="px-3 py-2">
                            @if ($registro->rubros > 0)
                                <button type="button" wire:click="abrirSincronizacion({{ $registro->id }}, {{ $registro->rubros }})" class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4">
                                    {{ number_format($registro->rubros, 0, ',', '.') }}
                                </button>
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-3 py-2 text-gray-600">{{ $registro->mensaje ?: $registro->origen }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-4 text-gray-500">Aún no hay sincronizaciones.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
