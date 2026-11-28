@extends('micrositio.layout')

@section('titulo', 'Sistema Integral de Información Departamental')

@section('contenido')
<section class="soft">
    <div class="wrap">
        <p class="tipo">Sistema Integral de Información Departamental</p>
        <h1>Indicadores del Meta</h1>
        <p>Consulte el catálogo institucional y los indicadores territoriales publicados.</p>
        <div class="indicator-catalog-layout">
            <aside class="card indicator-catalog-filters" aria-labelledby="catalog-filters-title">
                <form method="get">
                    <h2 id="catalog-filters-title">Filtrar indicadores</h2>
                    <label for="catalog-search">Buscar por código o nombre</label>
                    <input id="catalog-search" name="q" value="{{ $filtros['q'] }}" type="search" placeholder="Ej. población o ID-001">

                    <fieldset>
                        <legend>Tipo de indicador</legend>
                        @foreach (['' => 'Todos', 'siid' => 'SIID', 'territorial' => 'Territorial'] as $valor => $etiqueta)
                            <label><input type="radio" name="tipo" value="{{ $valor }}" @checked($filtros['tipo'] === $valor)> {{ $etiqueta }}</label>
                        @endforeach
                    </fieldset>

                    <fieldset>
                        <legend>Eje temático</legend>
                        <label><input type="radio" name="eje" value="" @checked($filtros['eje'] === '')> Todos</label>
                        @foreach ($ejes as $eje)
                            <label><input type="radio" name="eje" value="{{ $eje->codigo }}" @checked($filtros['eje'] === $eje->codigo)> {{ $eje->nombre }}</label>
                        @endforeach
                    </fieldset>

                    <div class="indicator-filter-actions">
                        <button class="btn" type="submit">Aplicar filtros</button>
                        <a class="btn out" href="{{ route('micrositio.indicadores') }}">Limpiar</a>
                    </div>
                </form>
            </aside>

            <div class="indicator-catalog-results">
                <p class="indicator-result-count" role="status">
                    @if ($publicaciones->total())
                        Mostrando {{ $publicaciones->firstItem() }}–{{ $publicaciones->lastItem() }} de {{ $publicaciones->total() }} indicadores
                    @else
                        No se encontraron indicadores
                    @endif
                </p>
                <div class="indicator-catalog-grid">
                    @forelse($publicaciones as $publicacion)
                        @php($indicador = $publicacion->publicable)
                        <article class="card ind">
                            @if($publicacion->imageUrl())<img src="{{ $publicacion->imageUrl() }}" alt="" loading="lazy" class="indicator-card-image">@endif
                            <span class="tipo">
                                @if ($indicador instanceof \App\Models\SiidIndicador)
                                    SIID · {{ $indicador->eje?->nombre ?? 'Sin eje' }}
                                @else
                                    Territorial · {{ $indicador->area_tematica ?: 'Sin eje' }}
                                @endif
                            </span>
                            @if($publicacion->destacado)<span class="badge-real">Destacado</span>@endif
                            <h2>{{ $publicacion->etiqueta ?: $indicador->etiqueta() }}</h2>
                            <p>{{ $publicacion->descripcion_publica ?: ($indicador->descripcion ?? $indicador->definicion ?? 'Consulte la ficha institucional del indicador.') }}</p>
                            <a class="btn out" href="{{ route('micrositio.siid.show', $publicacion) }}">Consultar indicador</a>
                        </article>
                    @empty
                        <div class="card indicator-empty-state">
                            <h2>Sin resultados</h2>
                            <p>No hay indicadores publicados que coincidan con los filtros seleccionados.</p>
                            <a class="btn out" href="{{ route('micrositio.indicadores') }}">Ver todos</a>
                        </div>
                    @endforelse
                </div>

                @if ($publicaciones->hasPages())
                    <nav class="catalog-pagination" aria-label="Paginación de indicadores">
                        @if ($publicaciones->onFirstPage())
                            <span aria-disabled="true">← Anterior</span>
                        @else
                            <a href="{{ $publicaciones->previousPageUrl() }}" rel="prev">← Anterior</a>
                        @endif

                        @foreach ($publicaciones->getUrlRange(max(1, $publicaciones->currentPage() - 2), min($publicaciones->lastPage(), $publicaciones->currentPage() + 2)) as $pagina => $url)
                            <a href="{{ $url }}" @if ($pagina === $publicaciones->currentPage()) aria-current="page" @endif>{{ $pagina }}</a>
                        @endforeach

                        @if ($publicaciones->hasMorePages())
                            <a href="{{ $publicaciones->nextPageUrl() }}" rel="next">Siguiente →</a>
                        @else
                            <span aria-disabled="true">Siguiente →</span>
                        @endif
                    </nav>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
