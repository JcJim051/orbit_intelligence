@props(['cifra', 'preview' => false])
@if ($cifra['clicable'])
    <button type="button" class="counter" data-contador="{{ $cifra['origen'] }}" data-etiqueta="{{ $cifra['etiqueta'] }}" data-url="{{ route('micrositio.contadores.detalle', ['origen' => $cifra['origen'], 'preview' => $preview ? 1 : null]) }}">
        <b>{{ $cifra['valor_mostrado'] }}</b>
        <span>{{ $cifra['etiqueta'] }}</span>
        <i>Ver detalle →</i>
    </button>
@else
    <article class="counter">
        <b>{{ $cifra['valor_mostrado'] }}</b>
        <span>{{ $cifra['etiqueta'] }}</span>
        @if ($cifra['es_ejemplo'])
            <em class="ej">ejemplo</em>
        @elseif (! $cifra['es_dinamico'] && $cifra['fuente'])
            <small>Fuente: {{ $cifra['fuente'] }}</small>
            @if ($cifra['corte'])
                <small>Fecha de corte: {{ $cifra['corte'] }}</small>
            @endif
        @endif
    </article>
@endif
