@extends('micrositio.layout')

@section('titulo', 'Geovisores — SIID')

@section('contenido')
<section class="wrap public-geo-page">
    <header class="public-geo-heading">
        <p class="eyebrow">Inteligencia geográfica</p>
        <h1>Geovisores publicados</h1>
        <p>Consulte mapas aprobados técnicamente por el equipo SIG de la Gobernación del Meta.</p>
        <label class="public-geo-search">Buscar geovisor <input type="search" data-geovisor-search placeholder="Nombre o descripción"></label>
    </header>
    <div class="public-geo-grid" data-geovisor-grid>
        @forelse ($publicaciones as $publicacion)
            <article class="public-geo-card" data-geovisor-card data-search="{{ mb_strtolower(($publicacion->titulo_publico ?: $publicacion->viewer->name).' '.$publicacion->descripcion_publica) }}">
                @if ($publicacion->imageUrl())<img src="{{ $publicacion->imageUrl() }}" alt="">@endif
                <div>
                    @if ($publicacion->destacado)<span class="public-geo-badge">Destacado</span>@endif
                    <h2>{{ $publicacion->titulo_publico ?: $publicacion->viewer->name }}</h2>
                    <p>{{ $publicacion->descripcion_publica ?: 'Geovisor institucional aprobado para consulta pública.' }}</p>
                    <a class="btn" href="{{ route('micrositio.geovisores.show', $publicacion->viewer) }}">Abrir geovisor</a>
                </div>
            </article>
        @empty
            <p>No hay geovisores visibles en este momento.</p>
        @endforelse
    </div>
</section>
<script>document.querySelector('[data-geovisor-search]')?.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('[data-geovisor-card]').forEach(c=>c.hidden=!c.dataset.search.includes(q))})</script>
@endsection
