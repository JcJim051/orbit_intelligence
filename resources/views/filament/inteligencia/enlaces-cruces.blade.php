@php
    $formatos = ['geojson' => 'GeoJSON', 'kml' => 'KML', 'shp' => 'Shapefile'];
@endphp
@if (! empty($todos))
    <p class="mt-2 text-sm">
        <span class="font-semibold">Todos los cruces:</span>
        @foreach ($formatos as $formato => $etiqueta)
            <a class="ml-2 font-semibold text-emerald-800 underline" href="{{ route('analisis.cruces', ['analisis' => $analisisId, 'formato' => $formato]) }}">{{ $etiqueta }}</a>
        @endforeach
    </p>
@elseif ($fila['pendiente'] ?? false)
    <p class="mt-2 text-sm text-amber-800">fuente no disponible</p>
@elseif (($fila['conteo'] ?? 0) > 0)
    <p class="mt-2 text-sm">
        @foreach ($formatos as $formato => $etiqueta)
            <a class="mr-2 font-semibold text-emerald-800 underline" href="{{ route('analisis.capa.cruces', ['analisis' => $analisisId, 'capa' => $fila['id'], 'formato' => $formato]) }}">{{ $etiqueta }}</a>
        @endforeach
    </p>
@endif
