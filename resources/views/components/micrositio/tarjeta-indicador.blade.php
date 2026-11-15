@props(['tarjeta', 'enlace' => null, 'textoEnlace' => null])
<article class="card">
    @if (! empty($tarjeta['insignia']))
        <span class="badge">{{ $tarjeta['insignia'] }}</span>
    @endif
    @if (! empty($tarjeta['es_ejemplo']))
        <span class="ej">ejemplo</span>
    @endif
    <h3>{{ $tarjeta['titulo'] }}</h3>
    @if (! empty($tarjeta['descripcion']))
        <p>{{ $tarjeta['descripcion'] }}</p>
    @endif
    @if (! empty($tarjeta['valor']))
        <div class="val">{{ $tarjeta['valor'] }}</div>
    @endif
    <div class="foot">
        <span>{{ $tarjeta['detalle'] ?? '' }}</span>
        <x-micrositio.delta :texto="$tarjeta['delta_texto'] ?? null" :tipo="$tarjeta['delta_tipo'] ?? 'neutro'" />
    </div>
    @if ($enlace)
        <a class="ix-btn ghost" href="{{ $enlace }}">{{ $textoEnlace ?: 'Consultar indicador' }}</a>
    @endif
</article>
