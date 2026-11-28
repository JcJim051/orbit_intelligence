<x-filament-panels::page>
    @php
        $indicator = $this->getRecord();
        $indicator->loadMissing('dataSource.currentVersion');
        $version = $indicator->dataSource?->currentVersion;
        $preview = array_slice($version?->records ?? [], 0, 10);
    @endphp

    <div class="space-y-6">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.2em] text-primary-600">{{ $indicator->codigo }}</p>
                    <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $indicator->nombre }}</h2>
                    <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-300">Defina la estructura una vez, descargue la plantilla y cargue cada actualización como una versión auditable.</p>
                </div>
                <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">
                    <span class="text-gray-500">Versión actual</span>
                    <strong class="ml-2 text-gray-950 dark:text-white">{{ $version?->version ?? 0 }}</strong>
                    <span class="mx-2 text-gray-300">·</span>
                    <span class="text-gray-500">{{ $version?->row_count ?? 0 }} registros</span>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h3 class="font-bold text-gray-950 dark:text-white">1. Columnas de la serie</h3>
                    <p class="text-sm text-gray-500">El identificador será el encabezado exacto de la plantilla.</p>
                </div>
                <button type="button" wire:click="addColumn" class="rounded-lg border border-primary-300 px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">+ Agregar columna</button>
            </div>

            <div class="space-y-3">
                @foreach ($columns as $index => $column)
                    <div wire:key="column-{{ $index }}" class="grid gap-3 rounded-lg bg-gray-50 p-3 md:grid-cols-[1.4fr_1fr_1fr_auto_auto] dark:bg-white/5">
                        <label class="text-xs font-semibold text-gray-600">Nombre visible
                            <input wire:model="columns.{{ $index }}.label" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="Ej. Municipio">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Identificador
                            <input wire:model="columns.{{ $index }}.key" type="text" class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="municipio">
                        </label>
                        <label class="text-xs font-semibold text-gray-600">Tipo
                            <select wire:model="columns.{{ $index }}.type" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                <option value="text">Texto</option><option value="integer">Entero</option><option value="decimal">Decimal</option><option value="date">Fecha</option><option value="boolean">Sí / No</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700"><input wire:model="columns.{{ $index }}.required" type="checkbox" class="rounded border-gray-300 text-primary-600"> Obligatoria</label>
                        <button type="button" wire:click="removeColumn({{ $index }})" class="self-end rounded-lg px-3 py-2 text-sm font-semibold text-danger-600 hover:bg-danger-50">Quitar</button>
                    </div>
                @endforeach
            </div>
            @error('columns') <p class="mt-3 text-sm text-danger-600">{{ $message }}</p> @enderror
            <button type="button" wire:click="saveSchema" wire:loading.attr="disabled" class="mt-4 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700">Guardar estructura</button>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="font-bold text-gray-950 dark:text-white">2. Descargar plantilla</h3>
                <p class="mt-1 text-sm text-gray-500">Contiene los encabezados exactos definidos arriba.</p>
                <button type="button" wire:click="downloadTemplate" class="mt-4 rounded-lg border border-primary-300 px-4 py-2 text-sm font-bold text-primary-700 hover:bg-primary-50">Descargar XLSX</button>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="font-bold text-gray-950 dark:text-white">3. Carga masiva</h3>
                <p class="mt-1 text-sm text-gray-500">CSV o XLSX, máximo 20 MB y 10.000 registros por versión.</p>
                <input wire:model="seriesFile" type="file" accept=".csv,.xlsx" class="mt-4 block w-full text-sm text-gray-600">
                @error('seriesFile') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
                <button type="button" wire:click="uploadSeries" wire:loading.attr="disabled" class="mt-4 rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-700">Validar y cargar</button>
            </section>
        </div>

        @if ($version && $preview !== [])
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-white/10"><h3 class="font-bold text-gray-950 dark:text-white">Vista previa de la versión {{ $version->version }}</h3></div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr>@foreach ($columns as $column)<th class="px-4 py-3">{{ $column['label'] }}</th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-gray-100">@foreach ($preview as $row)<tr>@foreach ($columns as $column)<td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ is_bool($row[$column['key']] ?? null) ? (($row[$column['key']] ?? false) ? 'Sí' : 'No') : ($row[$column['key']] ?? '—') }}</td>@endforeach</tr>@endforeach</tbody>
                </table></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
