<div class="space-y-3 text-sm">
    @include('filament.inteligencia.enlaces-cruces', ['analisisId' => $analisisId, 'todos' => true])
    @foreach ($filas as $fila)
        <div>
            <p class="font-semibold">{{ $fila['capa'] }}</p>
            @include('filament.inteligencia.enlaces-cruces', ['analisisId' => $analisisId, 'fila' => $fila])
        </div>
    @endforeach
</div>
