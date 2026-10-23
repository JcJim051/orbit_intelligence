<x-filament-panels::page>
    <p class="text-sm text-gray-600">
        Cargue el informe mensual de ejecución presupuestal de gastos. El código de fuente se cruza con el catálogo PCT y se agrupa en SGP, SGR, Recursos propios, Crédito, Otras de la Nación o Sin catálogo.
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
    @if ($corte)
        <p class="mt-4 text-xs text-gray-500">Corte cargado: {{ \Illuminate\Support\Carbon::parse($corte)->format('d/m/Y') }}</p>
    @endif
</x-filament-panels::page>
