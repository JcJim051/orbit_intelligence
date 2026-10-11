<div class="space-y-2 text-sm">
    <p>{{ $titulo }}: <strong>{{ $cantidad }}</strong></p>
    @foreach ($lineas as $linea)
        <p>{{ $linea }}</p>
    @endforeach
</div>
