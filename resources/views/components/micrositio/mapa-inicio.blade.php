@props(['mapa', 'preview' => false])
@php
    $principal = collect($mapa['capas'])->firstWhere('codigo', $mapa['capa_principal']) ?? ($mapa['capas'][0] ?? null);
    $consulta = ['v' => $mapa['version'] ?? 'bootstrap'];
    if ($preview) {
        $consulta['preview'] = 1;
    }
@endphp
<div class="mapa-inicio" data-mapa-inicio
    data-datos="{{ route('micrositio.mapa.datos', $consulta) }}"
    data-municipios="{{ route('micrositio.mapa.municipios', $consulta) }}"
    data-colombia="{{ route('micrositio.mapa.colombia', $consulta) }}"
    data-config="{{ json_encode(['capa' => $mapa['capa_principal'], 'paleta' => $mapa['paleta'], 'polaridad' => $mapa['polaridad'], 'texto' => $mapa['texto_tarjeta'], 'cta' => $mapa['texto_cta'], 'urlCta' => $mapa['url_cta'], 'urlFicha' => $mapa['url_ficha_tecnica'], 'urlMunicipio' => $mapa['url_indicadores_municipio']], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) }}">
    <div class="layerpills" role="tablist" aria-label="Capas del mapa"></div>
    <div class="maphead">
        <div>
            <h3></h3>
            <div class="meta"></div>
        </div>
        <div class="dep" aria-live="polite"></div>
    </div>
    <div class="mapbox">
        <svg class="mapa-svg" role="group" aria-label="Mapa de los 29 municipios del Meta"></svg>
        <svg class="inset" aria-label="Ubicación del Meta en Colombia"></svg>
        <div class="legend"></div>
        <div class="tip" role="tooltip"></div>
        <div class="float">
            <h5>Meta</h5>
            <p></p>
            <a class="ix-btn" href="{{ $mapa['url_cta'] ?: '#' }}">{{ $mapa['texto_cta'] }} →</a>
        </div>
        <aside class="side" role="dialog" aria-labelledby="mapa-municipio-titulo" hidden></aside>
    </div>
    <div class="mapa-fallback">
        <h4>Municipios del Meta{{ $principal ? ' — '.$principal['codigo'].' — '.$principal['nombre'] : '' }}</h4>
        <table>
            <caption>Valores municipales de la capa principal. Esta tabla permanece disponible si el mapa no carga.</caption>
            <thead><tr><th>Municipio</th><th>Valor</th></tr></thead>
            <tbody>
                @foreach ($principal['valores'] ?? [] as $fila)
                    <tr>
                        <td>{{ $fila['divipola'] }} — {{ $fila['municipio'] }}</td>
                        <td>
                            @if (($fila['valor'] ?? null) === null)
                                s. d.
                            @elseif (($principal['unidad'] ?? '') === '%')
                                {{ number_format((float) $fila['valor'], (int) ($principal['decimales'] ?? 0), ',', '.') }} %
                            @elseif (($principal['unidad'] ?? '') === 'barriles_mes')
                                {{ number_format((float) $fila['valor'], (int) ($principal['decimales'] ?? 0), ',', '.') }} barriles/mes
                            @elseif (($principal['unidad'] ?? '') === 'toneladas')
                                {{ number_format((float) $fila['valor'], (int) ($principal['decimales'] ?? 0), ',', '.') }} t
                            @else
                                {{ number_format((float) $fila['valor'], 0, ',', '.') }} hab.
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <noscript>
        <p>Active JavaScript para ver el mapa interactivo. La tabla anterior lista los municipios.</p>
    </noscript>
    <div class="modal-bg" id="modal-micrositio" hidden>
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
            <button type="button" class="x" data-cerrar-modal aria-label="Cerrar">✕</button>
            <div id="modal-cuerpo"></div>
        </div>
    </div>
</div>
