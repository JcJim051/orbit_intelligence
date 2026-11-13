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
            <h2>Indicadores por tema</h2>
            <p style="color:var(--muted)">Tableros interactivos con filtros; los datos se pueden descargar.</p>
            <div class="tabs" role="tablist" aria-label="Temas de indicadores">
                @foreach ($contenido['temas'] as $tema)
                    <button type="button" role="tab" id="tema-tab-{{ $tema['slug'] }}" aria-controls="tema-panel-{{ $tema['slug'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}" data-tema="{{ $tema['slug'] }}">{{ $tema['nombre'] }}</button>
                @endforeach
            </div>
            @foreach ($contenido['temas'] as $tema)
            <div class="grid theme-panel" role="tabpanel" id="tema-panel-{{ $tema['slug'] }}" aria-labelledby="tema-tab-{{ $tema['slug'] }}" tabindex="0" data-theme-panel="{{ $tema['slug'] }}" @if (!$loop->first) hidden @endif>
                @foreach ($contenido['indicadores'] as $indicador)
                    @continue(! $indicador['es_publico'] || $indicador['tema'] !== $tema['slug'])
                    <a class="card ind indicator-card" data-tema="{{ $indicador['tema'] }}" href="{{ route('micrositio.indicadores', array_filter(['i' => $indicador['codigo'], 'preview' => $contenido['preview'] ? 1 : null])) }}" aria-labelledby="indicador-titulo-{{ $indicador['codigo'] }}">
                        @if ($indicador['es_ejemplo'])
                            <span class="badge-ej">dato de ejemplo</span>
                        @else
                            <span class="badge-real">dato real</span>
                        @endif
                        <h3 id="indicador-titulo-{{ $indicador['codigo'] }}">{{ $indicador['etiqueta'] }}</h3>
                        <p>{{ $indicador['descripcion'] }}</p>
                        <span class="btn out" aria-hidden="true">Ver tablero</span>
                    </a>
                @endforeach
            </div>
            @endforeach
            <p style="margin-top:24px"><a class="btn" href="{{ route('micrositio.datos-abiertos') }}">Descargar datos (CSV) — pasiva</a></p>
        </div>
    </section>
 
@endsection
