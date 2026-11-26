<div class="space-y-4">
    @if($record->error_general)
        <div class="rounded-lg bg-danger-50 p-3 text-sm text-danger-700">{{ $record->error_general }}</div>
    @endif
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach(['creados' => 'Nuevos', 'actualizados' => 'Actualizados', 'inactivados' => 'Inactivados', 'conflictos' => 'Conflictos'] as $campo => $etiqueta)
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">{{ $etiqueta }}</div><div class="text-xl font-semibold">{{ $record->{$campo} }}</div></div>
        @endforeach
    </div>
    <div class="max-h-96 overflow-auto rounded-lg border">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-gray-50"><tr><th class="p-2">Tipo</th><th class="p-2">Origen</th><th class="p-2">Indicador</th><th class="p-2">Detalle</th></tr></thead>
            <tbody>
            @forelse($record->detalles as $detalle)
                <tr class="border-t"><td class="p-2">{{ $detalle->tipo }}</td><td class="p-2">{{ $detalle->pestana }}{{ $detalle->fila ? ' · fila '.$detalle->fila : '' }}</td><td class="p-2">{{ $detalle->codigo ?: '—' }}</td><td class="p-2">{{ $detalle->motivo }}</td></tr>
            @empty
                <tr><td colspan="4" class="p-4 text-center text-gray-500">Esta ejecución no registró novedades.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
