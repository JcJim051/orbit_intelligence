@extends('micrositio.layout')

@section('contenido')
    <x-micrositio.hero :hero="$contenido['hero']" :cifras="$contenido['cifras']" :mapa="$contenido['mapa']" :preview="$contenido['preview']" />

    <section class="blk" id="ultimos">
        <div class="wrap">
            <x-micrositio.seccion-encabezado
                :titulo="$contenido['textos']['ultimos_titulo']"
                :bajada="$contenido['textos']['ultimos_bajada']"
                :enlace="$contenido['textos']['ultimos_enlace']"
                :url="route('micrositio.indicadores')" />
            <div class="cards">
                @foreach ($contenido['ultimos'] as $tarjeta)
                    <x-micrositio.tarjeta-indicador :tarjeta="$tarjeta" :enlace="$tarjeta['url']" />
                @endforeach
            </div>
        </div>
    </section>

    <section class="blk" id="destacados">
        <div class="wrap">
            <x-micrositio.seccion-encabezado
                :titulo="$contenido['textos']['destacados_titulo']"
                :bajada="$contenido['textos']['destacados_bajada']"
                :enlace="$contenido['textos']['destacados_enlace']"
                :url="route('micrositio.indicadores')" />
            @if ($indicadoresDestacadosPorEje->isNotEmpty())
                <div class="indicator-axis-tabs" role="tablist" aria-label="Ejes de indicadores destacados">
                    @foreach ($indicadoresDestacadosPorEje as $eje => $areas)
                        <button type="button" role="tab" id="eje-tab-{{ $loop->index }}" aria-controls="eje-panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-indicator-axis-button="{{ $loop->index }}" class="{{ $loop->first ? 'is-active' : '' }}">{{ $eje }}</button>
                    @endforeach
                </div>
                @foreach ($indicadoresDestacadosPorEje as $eje => $areas)
                    <div role="tabpanel" id="eje-panel-{{ $loop->index }}" aria-labelledby="eje-tab-{{ $loop->index }}" data-indicator-axis-panel="{{ $loop->index }}" @if (! $loop->first) hidden @endif>
                        @foreach ($areas as $area => $publicaciones)
                            <div class="area-block">
                                <p class="tipo">Área temática</p>
                                <h3>{{ $area }}</h3>
                                <div class="cards">
                                    @foreach ($publicaciones as $publicacion)
                                        @php($indicador = $publicacion->publicable)
                                        <x-micrositio.tarjeta-indicador
                                            :tarjeta="[
                                                'insignia' => $indicador->codigo ?? '',
                                                'titulo' => $publicacion->etiqueta ?: $indicador->etiqueta(),
                                                'descripcion' => $publicacion->descripcion_publica ?: ($indicador->descripcion ?? $indicador->definicion ?? 'Consulte la ficha institucional del indicador.'),
                                                'detalle' => 'Destacado',
                                                'es_ejemplo' => false,
                                            ]"
                                            :enlace="route('micrositio.siid.show', $publicacion)"
                                            texto-enlace="Consultar indicador" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @else
                <p class="sub">Todavía no hay indicadores destacados para mostrar.</p>
            @endif
        </div>
    </section>

    <section class="blk pubs" id="publicaciones">
        <div class="wrap">
            <x-micrositio.seccion-encabezado
                :titulo="$contenido['textos']['publicaciones_titulo']"
                :bajada="$contenido['textos']['publicaciones_bajada']"
                :enlace="$contenido['textos']['publicaciones_enlace']"
                :url="route('micrositio.inicio').'#publicaciones'" />
            <div class="pubgrid">
                @foreach (array_slice($contenido['publicaciones'], 0, 3) as $publicacion)
                    <x-micrositio.tarjeta-publicacion :publicacion="$publicacion" :alterna="! $loop->first" />
                @endforeach
            </div>
        </div>
    </section>

    <section class="blk" id="aliados">
        <div class="wrap">
            <x-micrositio.seccion-encabezado
                :titulo="$contenido['textos']['aliados_titulo']"
                :bajada="$contenido['textos']['aliados_bajada']" />
            <x-micrositio.aliados :aliados="$contenido['aliados']" />
        </div>
    </section>
@endsection
