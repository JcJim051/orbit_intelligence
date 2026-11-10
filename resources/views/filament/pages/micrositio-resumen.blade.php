<x-filament-panels::page>
    <p class="text-sm text-gray-600">El borrador se edita en cada sección. El sitio público cambia solo cuando se publica esa sección.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-3">
        @foreach ($secciones as $seccion)
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <p class="text-sm text-gray-500">{{ $seccion['titulo'] }}</p>
                @if ($seccion['total'] > 0)
                    <button type="button" wire:click="abrirDetalle('{{ $seccion['clave'] }}')" class="mt-1 text-3xl font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-4">
                        {{ number_format($seccion['total'], 0, ',', '.') }}
                    </button>
                @else
                    <p class="mt-1 text-3xl font-semibold text-gray-400">0</p>
                @endif
                <p class="mt-2 text-xs {{ $seccion['publicada'] ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $seccion['publicada'] ? 'Publicado' : 'Borrador con cambios o sin publicar' }}
                </p>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
