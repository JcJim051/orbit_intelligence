<div class="space-y-2 text-sm">
    <p>{{ $titulo }}</p>
    <ul class="max-h-80 space-y-1 overflow-auto">
        @forelse ($lineas as $linea)
            <li>{{ $linea }}</li>
        @empty
            <li>No hay registros en este conteo.</li>
        @endforelse
    </ul>
</div>
