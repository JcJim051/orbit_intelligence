@extends('micrositio.layout')

@section('titulo', ($publicacion->titulo_publico ?: $geoViewer->name).' — SIID')

@section('contenido')
<section class="wrap public-geo-page">
    <header class="public-geo-heading">
        <p class="eyebrow">Geovisor institucional</p>
        <h1>{{ $publicacion->titulo_publico ?: $geoViewer->name }}</h1>
        <p>{{ $publicacion->descripcion_publica ?: 'Información geográfica aprobada por el equipo SIG.' }}</p>
    </header>
    <div class="public-viewer-frame"><iframe src="{{ route('geo-viewers.embed', $geoViewer) }}" title="{{ $geoViewer->name }}" loading="lazy" allowfullscreen></iframe></div>
</section>
@endsection
