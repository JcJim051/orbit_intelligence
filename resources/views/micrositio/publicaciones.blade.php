@extends('micrositio.layout')
@section('titulo', 'Publicaciones — Repositorio de documentos')
@section('contenido')
@php
    $documentos = $contenido['publicaciones'];
    $tipos = array_values(array_unique(array_merge(\App\Models\MicrositioPublicacion::TIPOS, array_column($documentos, 'tipo'))));
    $anios = array_values(array_unique(array_filter(array_map(fn ($doc) => substr($doc['fecha'], 0, 4), $documentos))));
    rsort($anios);
@endphp
<div data-publication-repository>
<div class="repository-search"><div class="wrap"><label for="publication-query">Buscar publicaciones</label><input id="publication-query" type="search" placeholder="Buscar documentos, fichas, anuarios…" data-publication-query></div></div>
<section class="wrap repository">
<h1>Repositorio de documentos</h1>

<div class="repository-layout"><aside aria-label="Filtrar publicaciones">
<fieldset><legend>Tipo</legend>@foreach($tipos as $tipo)<label><input type="checkbox" data-publication-type value="{{ $tipo }}"> {{ $tipo }}</label>@endforeach</fieldset>
<fieldset><legend>Año</legend>@foreach($anios as $anio)<label><input type="checkbox" data-publication-year value="{{ $anio }}"> {{ $anio }}</label>@endforeach</fieldset>
<button type="button" class="btn out" data-publication-clear>Limpiar filtros</button>
</aside><div>
<div class="repository-filter-tags" data-publication-tags role="group" aria-label="Filtros activos" hidden></div>
<div class="repository-summary"><p role="status" aria-live="polite" data-publication-count>{{ count($documentos) }} documentos</p><div><label class="repository-sr-only" for="publication-sort">Ordenar por</label> <select id="publication-sort" data-publication-sort><option value="recent">Más recientes</option><option value="old">Más antiguos</option><option value="title">Título A–Z</option></select></div></div>
<div class="repository-results" data-publication-results>
@foreach($documentos as $documento)
<article class="repository-document" data-publication-document data-title="{{ $documento['titulo'] }}" data-type="{{ $documento['tipo'] }}" data-year="{{ substr($documento['fecha'], 0, 4) }}" data-date="{{ $documento['fecha'] }}">
    <span class="repository-format" data-format="{{ $documento['formato'] }}" aria-label="Formato {{ $documento['formato'] }}">
        <svg viewBox="0 0 32 40" aria-hidden="true"><path d="M3 0h18l11 11v26a3 3 0 0 1-3 3H3a3 3 0 0 1-3-3V3a3 3 0 0 1 3-3Z" fill="currentColor"/><path d="M21 0v11h11" fill="white" opacity=".4"/></svg>
        <span>{{ $documento['formato'] }}</span>
    </span>
    <div class="repository-description">
        <h2>{{ $documento['titulo'] }}</h2><p>{{ $documento['resumen'] }}</p>
        <ul aria-label="Información del documento"><li class="repository-type" data-type="{{ $documento['tipo'] }}">{{ $documento['tipo'] }}</li><li><time datetime="{{ $documento['fecha'] }}" title="{{ $documento['fecha'] }}">{{ substr($documento['fecha'], 0, 4) }}</time></li>@if($documento['peso'])<li>{{ $documento['peso'] }}</li>@endif</ul>
        @if($documento['es_ejemplo'])<span class="badge-ej">dato de ejemplo</span>@endif
    </div>
    <div class="repository-actions">
        @if($documento['vista_previa_url'])
            <button type="button" class="btn out" data-pdf-preview="{{ $documento['vista_previa_url'] }}" data-document-name="{{ $documento['titulo'] }}" data-download-url="{{ $documento['archivo_url'] }}" aria-label="Ver {{ $documento['titulo'] }}">Ver</button>
        @endif
        @if($documento['archivo_url'])
            <a class="btn" href="{{ $documento['archivo_url'] }}" download aria-label="Descargar {{ $documento['titulo'] }}">Descargar</a>
        @else
            <small>Archivo pendiente</small>
        @endif
    </div>
</article>
@endforeach
</div>
<nav class="repository-pagination" data-publication-pagination aria-label="Páginas de publicaciones"></nav>
@include('micrositio.pdf-viewer')<p data-publication-empty hidden>{{ $documentos ? 'No se encontraron documentos. Prueba otra búsqueda o limpia los filtros.' : 'Todavía no hay publicaciones disponibles.' }}</p>
</div></div></section></div>
@endsection
