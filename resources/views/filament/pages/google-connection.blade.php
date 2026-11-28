<x-filament-panels::page>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form wire:submit="save" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Google Sheets</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Origen del catálogo SIID</h2>
                <p class="mt-2 text-sm text-slate-600">La credencial se cifra en la base de datos y nunca se muestra nuevamente.</p>
            </div>

            <label class="block">
                <span class="mb-1 block text-sm font-bold text-slate-700">ID del documento</span>
                <input wire:model="spreadsheetId" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="1Y8j1Q...">
                @error('spreadsheetId') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-bold text-slate-700">Pestañas principales</span>
                    <textarea wire:model="mainTabs" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Una pestaña por línea"></textarea>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-bold text-slate-700">Pestañas complementarias</span>
                    <textarea wire:model="complementaryTabs" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Una pestaña por línea"></textarea>
                </label>
            </div>

            <label class="block rounded-xl border border-dashed border-emerald-300 bg-emerald-50/60 p-4">
                <span class="block text-sm font-bold text-emerald-900">Credencial de cuenta de servicio (.json)</span>
                @if($credentialsFilename)
                    <span class="mt-1 block text-xs text-emerald-700">Configurada: {{ $credentialsFilename }}</span>
                @endif
                <input wire:model="credentialsFile" type="file" accept=".json,application/json" class="mt-3 block w-full text-sm text-slate-600">
                @error('credentialsFile') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-800">Guardar configuración</button>
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled" class="rounded-xl border border-emerald-300 bg-white px-5 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-50">Probar conexión</button>
                <button type="button" wire:click="synchronize" wire:loading.attr="disabled" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Sincronizar catálogo</button>
            </div>
        </form>

        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Estado</p>
                <div class="mt-3 flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full {{ $lastStatus === 'conectado' ? 'bg-emerald-500' : ($lastStatus === 'error' ? 'bg-rose-500' : 'bg-slate-300') }}"></span>
                    <strong class="text-sm text-slate-900">{{ $lastStatus === 'conectado' ? 'Conectado' : ($lastStatus === 'error' ? 'Con error' : 'Sin verificar') }}</strong>
                </div>
                @if($lastTestedAt)<p class="mt-2 text-xs text-slate-500">Última prueba: {{ $lastTestedAt }}</p>@endif
                @if($lastError)<p class="mt-3 rounded-lg bg-rose-50 p-3 text-xs leading-5 text-rose-700">{{ $lastError }}</p>@endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Cuenta de servicio</p>
                <p class="mt-3 break-all text-sm font-semibold text-slate-800">{{ $serviceAccountEmail ?: 'No configurada' }}</p>
                <p class="mt-3 text-xs leading-5 text-slate-500">Comparta el documento de Google Sheets con este correo en modo lector.</p>
            </section>
        </aside>
    </div>
</x-filament-panels::page>
