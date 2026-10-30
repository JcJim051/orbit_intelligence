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
            <div class="tabs">
                @foreach ($contenido['temas'] as $tema)
                    <button type="button" data-tema="{{ $tema['slug'] }}">{{ $tema['nombre'] }}</button>
                @endforeach
            </div>
            <div class="grid">
                @foreach ($contenido['indicadores'] as $indicador)
                    @continue(! $indicador['es_publico'])
                    <article class="card ind" data-tema="{{ $indicador['tema'] }}">
                        @if ($indicador['es_ejemplo'])
                            <span class="badge-ej">dato de ejemplo</span>
                        @else
                            <span class="badge-real">dato real</span>
                        @endif
                        <h3>{{ $indicador['etiqueta'] }}</h3>
                        <p>{{ $indicador['descripcion'] }}</p>
                        <a class="btn out" href="{{ route('micrositio.indicadores', array_filter(['i' => $indicador['codigo'], 'preview' => $contenido['preview'] ? 1 : null])) }}">Ver tablero</a>
                    </article>
                @endforeach
            </div>
            <p style="margin-top:24px"><a class="btn" href="{{ route('micrositio.datos-abiertos') }}">Descargar datos (CSV) — pasiva</a></p>
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
                        <p style="font-size:.85rem;color:var(--muted)">{{ $publicacion['resumen'] }}</p>
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
