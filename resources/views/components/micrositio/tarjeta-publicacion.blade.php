@props(['publicacion', 'alterna' => false])
@php
    $fecha = $publicacion['fecha'];
    $momento = null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $fecha, $partes)) {
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $momento = ($meses[(int) $partes[2]] ?? $partes[2]).' '.$partes[1];
    }
@endphp
<article class="pub">
    <div @class(['cover', 'alt' => $alterna])>
        <span>{{ mb_strtoupper($publicacion['tipo']) }}</span>
        <span>{{ $momento ?: $fecha }}</span>
    </div>
    <div>
        <div class="date">{{ $momento ?: $fecha }} · {{ $publicacion['tipo'] }}
            @if ($publicacion['es_ejemplo'])
                <span class="ej">ejemplo</span>
            @endif
        </div>
        <h3>{{ $publicacion['titulo'] }}</h3>
        <p>{{ $publicacion['resumen'] }}</p>
        @if ($publicacion['archivo_url'])
            <a class="ix-btn ghost" href="{{ $publicacion['archivo_url'] }}">{{ str_contains(mb_strtolower($publicacion['tipo']), 'boletín') || str_contains(mb_strtolower($publicacion['tipo']), 'boletin') ? 'Leer' : 'Descargar' }}</a>
        @endif
    </div>
</article>
