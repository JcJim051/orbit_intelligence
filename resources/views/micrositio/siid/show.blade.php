@extends('micrositio.layout')

@section('titulo', $indicador->etiqueta())

@section('contenido')
@php($serieDatosUrl = $publicacion->url_serie_datos ?: ($indicador instanceof \App\Models\SiidIndicador ? $indicador->url_datos : null))
@php($urlFuente = $publicacion->url_fuente ?: ($indicador instanceof \App\Models\SiidIndicador ? $indicador->url_fuente : null))
@php($ejeVisual = $indicador instanceof \App\Models\SiidIndicador ? strtolower((string) $indicador->eje?->codigo) : 'territorial')
<section class="soft indicator-page indicator-page--{{ str_replace('_', '-', $ejeVisual) }}">
    <div class="wrap">
        <p style="margin-bottom:22px"><a href="{{ route('micrositio.indicadores') }}">← Volver al catálogo</a></p>
        <div class="card indicator-axis-hero" style="padding:clamp(24px,4vw,48px);border-top:5px solid var(--primary)">
            <div class="indicator-hero-grid {{ $imagenUrl ? 'has-image' : '' }}">
                <div style="max-width:900px">
                    <p class="tipo">{{ $indicador instanceof \App\Models\SiidIndicador ? ($indicador->eje?->nombre ?? 'Indicador SIID') : 'Indicador territorial' }}</p>
                    <h1 style="margin-top:8px">{{ $publicacion->etiqueta ?: $indicador->etiqueta() }}</h1>
                    @if($descripcionPublica)<p style="font-size:1.05rem;line-height:1.7;color:var(--muted);margin-top:16px">{{ $descripcionPublica }}</p>@endif
                    @if($publicacion->destacado || $indicador instanceof \App\Models\IndicadorTerritorial || ($indicador instanceof \App\Models\SiidIndicador && ($indicador->es_icet || $indicador->es_pet)))
                        <p style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
                            @if($publicacion->destacado)<span class="badge-real">Indicador destacado</span>@endif
                            @if($indicador instanceof \App\Models\IndicadorTerritorial)<span class="badge-real">Indicador territorial</span>@endif
                            @if($indicador instanceof \App\Models\SiidIndicador && $indicador->es_icet)<span class="badge-real">ICET</span>@endif
                            @if($indicador instanceof \App\Models\SiidIndicador && $indicador->es_pet)<span class="badge-real">PET</span>@endif
                        </p>
                    @endif
                </div>
                @if($imagenUrl)<img src="{{ $imagenUrl }}" alt="Imagen representativa de {{ $publicacion->etiqueta ?: $indicador->etiqueta() }}" style="width:100%;max-height:300px;object-fit:cover;border-radius:18px" loading="eager">@endif
            </div>

            <div class="grid" style="margin-top:30px">
                @if($fuentePublica)
                    <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Fuente</span><p style="margin:8px 0 0"><b>{{ $fuentePublica }}</b></p></article>
                @endif
                @if($periodicidadPublica)
                    <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Periodicidad</span><p style="margin:8px 0 0"><b>{{ $periodicidadPublica }}</b></p></article>
                @endif
                @if($indicador->unidad_medida)
                    <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Unidad de medida</span><p style="margin:8px 0 0"><b>{{ $indicador->unidad_medida }}</b></p></article>
                @endif
                @if($indicador instanceof \App\Models\SiidIndicador && $indicador->anio_actualizacion)
                    <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Última actualización</span><p style="margin:8px 0 0"><b>{{ $indicador->anio_actualizacion }}</b></p></article>
                @endif
                <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Área temática</span><p style="margin:8px 0 0"><b>{{ $indicador instanceof \App\Models\SiidIndicador ? ($indicador->area?->nombre ?? 'Por clasificar') : ($indicador->area_tematica ?? 'Por clasificar') }}</b></p></article>
                @if($indicador instanceof \App\Models\IndicadorTerritorial && ($indicador->dependencia || $indicador->dependencia_fuente))
                    <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Dependencia responsable</span><p style="margin:8px 0 0"><b>{{ $indicador->dependencia?->nombre ?? $indicador->dependencia_fuente }}</b></p></article>
                @endif
                @if($indicador instanceof \App\Models\IndicadorTerritorial && $indicador->cobertura_geografica)
                    <article style="padding:18px;border-radius:14px;background:var(--soft)"><span class="tipo">Cobertura</span><p style="margin:8px 0 0"><b>{{ $indicador->cobertura_geografica }}</b></p></article>
                @endif
            </div>

            @if($indicador->url_ficha || $serieDatosUrl || $urlFuente)
                <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
                    @if($indicador->url_ficha)
                        <a class="btn out" href="{{ $indicador->url_ficha }}" target="_blank" rel="noopener">Consultar ficha técnica</a>
                    @endif
                    @if($serieDatosUrl)
                        <a class="btn" href="{{ $serieDatosUrl }}" target="_blank" rel="noopener">Consultar serie de datos</a>
                    @endif
                    @if($urlFuente)
                        <a class="btn out" href="{{ $urlFuente }}" target="_blank" rel="noopener">Consultar fuente</a>
                    @endif
                </div>
            @endif
        </div>

        @if($indicador instanceof \App\Models\IndicadorTerritorial && $publicacion->mostrar_serie)
            @include('micrositio.siid.territorial-series')
        @endif

        @if($tableroReferencia || $indicador instanceof \App\Models\SiidIndicador)
            <div style="margin-top:28px">
                <p class="tipo">Visualización</p>
                <h2 style="margin:6px 0 18px">Tablero del indicador</h2>
            </div>
            @if($lookerValido)
                <div class="card dashboard-frame-container" data-dashboard-container>
                    <div class="dashboard-loading" data-dashboard-loader role="status">Cargando tablero interactivo…</div>
                    <iframe data-looker-frame title="Tablero de {{ $indicador->etiqueta() }}" src="{{ $tableroReferencia }}" allowfullscreen></iframe>
                </div>
            @elseif($supersetValido && filled(config('superset.url')))
                <div class="card" style="padding:8px"><div id="embed" data-superset data-token-url="{{ route('micrositio.siid.token', $publicacion) }}" data-dominio="{{ config('superset.url') }}" style="min-height:720px"></div></div>
            @elseif($tableroReferencia)
                <div class="card">El tablero configurado no cumple las reglas de inserción segura. El equipo webmaster debe revisarlo.</div>
            @else
                <div class="card">Este indicador todavía no tiene un tablero publicado.</div>
            @endif
        @endif
    </div>
</section>
@endsection
