@extends('micrositio.layout')

@section('titulo', data_get($seleccionado, 'nombre', 'Indicadores'))

@section('contenido')
    <div class="ilayout">
        <aside class="ilist">
            <h2 class="sidebar-heading">Indicadores</h2>
            <form data-indicator-search role="search" aria-label="Buscar indicadores">
                <label for="indicator-search">Buscar indicador</label>
                <div class="indicator-search-controls">
                    <input id="indicator-search" type="search" placeholder="Nombre o código" aria-describedby="indicator-search-help" autocomplete="off">
                    <button type="button" data-search-clear aria-label="Limpiar búsqueda" hidden>×</button>
                    <button type="submit" aria-label="Buscar">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="10" cy="10" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="m15 15 6 6" stroke="currentColor" stroke-width="2"/></svg>
                    </button>
                </div>
                <p id="indicator-search-help" class="search-help">La lista se filtra mientras escribes.</p>
                <p data-search-status role="status" aria-live="polite" class="search-help"></p>
            </form>
            @foreach ($indicadores as $indicador)
                <a data-busqueda class="{{ ($seleccionado['codigo'] ?? null) === $indicador['codigo'] ? 'on' : '' }}" href="{{ route('micrositio.indicadores', array_filter(['i' => $indicador['codigo'], 'preview' => $preview ? 1 : null])) }}">
                    <b>{{ $indicador['etiqueta'] }}</b>
                    @if ($indicador['es_ejemplo'])
                        <span class="badge-ej">dato de ejemplo</span>
                    @else
                        <span class="badge-real">dato real</span>
                    @endif
                </a>
            @endforeach
        </aside>
        <div>
            @if ($seleccionado)
                <div class="indicador-cabecera">
                    <div>
                        <h1>{{ $seleccionado['etiqueta'] }}
                            @if ($seleccionado['es_ejemplo'])
                                <span class="badge-ej">dato de ejemplo</span>
                            @endif
                        </h1>
                        <p style="color:var(--muted);margin:0 0 12px">{{ $seleccionado['descripcion'] }} Acceso público de solo lectura, sin inicio de sesión.</p>
                    </div>
                    <a class="btn" href="{{ route('micrositio.indicadores.csv', array_filter(['codigo' => $seleccionado['codigo'], 'preview' => $preview ? 1 : null])) }}">Descargar datos (CSV)</a>
                </div>
                @if ($seleccionado['dashboard_uuid'] && filled(config('superset.url')))
                    <div
                        id="embed"
                        data-superset
                        data-token-url="{{ $preview ? route('micrositio.tableros.token', $seleccionado['codigo']) : route('micrositio.indicadores.token', $seleccionado['codigo']) }}"
                        data-dominio="{{ config('superset.url') }}"
                    ></div>
                @else
                    <p>Este indicador aún no tiene un tablero de Superset configurado.</p>
                @endif
            @else
                <h1>Indicadores</h1>
                <p>No hay indicadores publicados.</p>
            @endif
        </div>
    </div>
@endsection
