@extends('micrositio.layout')

@section('contenido')
    @php($titulo = $contenido['hero']['titulo'] ?: 'Gerencia de Información y Estudios Económicos')
    <div class="hero" @if ($contenido['hero']['banner_url']) style="background-image: linear-gradient(rgba(0,80,40,.45), rgba(0,80,40,.45)), url('{{ $contenido['hero']['banner_url'] }}')" @endif>
        <div class="wrap">
            <h1>{{ $contenido['hero']['titulo'] }}</h1>
            <p>{{ $contenido['hero']['subtitulo'] }}</p>
        </div>
    </div>
    <div class="wrap cifras">
        @forelse ($contenido['cifras'] as $cifra)
            <article class="card cifra">
                <b>{{ $cifra['valor'] }}</b>
                <p>{{ $cifra['descripcion'] }}</p>
                <small>Fuente: {{ $cifra['fuente'] }}</small>
                <small>Fecha de corte: {{ $cifra['corte'] }}</small>
                @if ($cifra['es_ejemplo'])
                    <span class="badge-ej">dato de ejemplo</span>
                @else
                    <span class="badge-real">dato real</span>
                @endif
            </article>
        @empty
            <p>Esta sección aún no se publica.</p>
        @endforelse
    </div>
    <section>
        <div class="wrap">
            <h2>Indicadores destacados por área temática</h2>
            <p style="color:var(--muted)">Una selección de los indicadores más relevantes del SIID.</p>
            @if($indicadoresDestacadosPorEje->isNotEmpty())
                <div class="indicator-axis-tabs" role="tablist" aria-label="Ejes de indicadores destacados">
                    @foreach ($indicadoresDestacadosPorEje as $eje => $areas)
                        <button type="button" role="tab" id="eje-tab-{{ $loop->index }}" aria-controls="eje-panel-{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-indicator-axis-button="{{ $loop->index }}" class="{{ $loop->first ? 'is-active' : '' }}">{{ $eje }}</button>
                    @endforeach
                </div>
                @foreach ($indicadoresDestacadosPorEje as $eje => $areas)
                    <div role="tabpanel" id="eje-panel-{{ $loop->index }}" aria-labelledby="eje-tab-{{ $loop->index }}" data-indicator-axis-panel="{{ $loop->index }}" @if(!$loop->first) hidden @endif>
                        @foreach ($areas as $area => $publicaciones)
                            <div style="margin-top:30px">
                                <p class="tipo">Área temática</p>
                                <h3 style="margin:5px 0 16px">{{ $area }}</h3>
                                <div class="grid">
                                    @foreach ($publicaciones as $publicacion)
                                        @php($indicador = $publicacion->publicable)
                                        <article class="card ind">
                                            @if($publicacion->imageUrl())<img src="{{ $publicacion->imageUrl() }}" alt="Imagen alusiva de {{ $publicacion->etiqueta ?: $indicador->etiqueta() }}" loading="lazy" style="width:100%;height:180px;object-fit:cover;border-radius:12px;margin-bottom:18px">@endif
                                            <span class="badge-real">Destacado</span>
                                            <h3>{{ $publicacion->etiqueta ?: $indicador->etiqueta() }}</h3>
                                            <p>{{ $publicacion->descripcion_publica ?: ($indicador->descripcion ?? $indicador->definicion ?? 'Consulte la ficha institucional del indicador.') }}</p>
                                            <a class="btn out" href="{{ route('micrositio.siid.show', $publicacion) }}">Consultar indicador</a>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @else
                <p style="margin-top:24px">Todavía no hay indicadores destacados para mostrar.</p>
            @endif
            <p style="margin-top:24px"><a class="btn out" href="{{ route('micrositio.indicadores') }}">Ver catálogo de indicadores</a></p>
        </div>
    </section>
    <section class="soft" id="publicaciones">
        <div class="wrap">
            <h2>Estudios y publicaciones</h2>
            <div class="grid" style="margin-top:18px">
                @foreach ($contenido['publicaciones'] as $publicacion)
                    <article class="card pub">
                        <span class="tipo">{{ $publicacion['tipo'] }}</span>
                        @if ($publicacion['es_ejemplo'])
                            <span class="badge-ej">dato de ejemplo</span>
                        @endif
                        <h3>{{ $publicacion['titulo'] }}</h3>
                        <p class="publication-summary">{{ $publicacion['resumen'] }}</p>
                        <small>{{ $publicacion['fecha'] }}</small>
                        @if ($publicacion['archivo_url'])
                            <p><a href="{{ $publicacion['archivo_url'] }}">Descargar archivo</a></p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endsection
