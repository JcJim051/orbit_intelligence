<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Micrositio · Inicio · Mapa del inicio</p>
        <span @class([
            'rounded-full px-3 py-1 text-xs font-semibold',
            'bg-emerald-50 text-emerald-700' => $estado === 'publicado',
            'bg-amber-50 text-amber-700' => $estado !== 'publicado',
        ])>{{ $estado === 'publicado' ? 'Publicado' : 'Borrador' }}</span>
    </div>
    <div class="grid items-start gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
        <form wire:submit="guardar" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            {{ $this->editorForm }}
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button type="submit">Guardar cambios</x-filament::button>
                <x-filament::button type="button" color="gray" wire:click="restablecer">Restablecer</x-filament::button>
            </div>
            <p class="mt-4 rounded-xl bg-blue-50 p-3 text-sm text-blue-900">
                Los datos productivos se actualizan automáticamente desde ANH y UPRA. Una respuesta inválida nunca reemplaza la última versión correcta.
            </p>
        </form>
        <section class="sticky top-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-950">Vista previa</h2>
                <span class="text-xs font-semibold text-primary-600">Se actualiza al guardar el borrador</span>
            </div>
            <div class="mapa-preview relative overflow-hidden rounded-xl border border-gray-200 bg-white" style="height:470px" data-mapa-preview>
                <iframe title="Vista previa del inicio" src="{{ $vistaPrevia }}&v={{ $this->vista }}" style="width:1440px;height:870px;border:0;transform-origin:0 0"></iframe>
            </div>
        </section>
    </div>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-gray-950">Fuentes y sincronizaciones</h2>
            <p class="mt-1 text-sm text-gray-500">Estado de las capas públicas. Las acciones de actualización están disponibles únicamente para administradores.</p>
        </div>
        {{ $this->table }}
    </section>
    <script>
        (() => {
            const marco = document.querySelector('[data-mapa-preview]');
            const iframe = marco?.querySelector('iframe');
            if (!marco || !iframe) return;
            const escalar = () => {
                const escala = marco.clientWidth / 1440;
                iframe.style.transform = `scale(${escala})`;
                marco.style.height = `${840 * escala}px`;
            };
            escalar();
            window.addEventListener('resize', escalar);
        })();
    </script>
</x-filament-panels::page>
