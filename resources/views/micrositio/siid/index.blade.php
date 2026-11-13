@extends('micrositio.layout')

@section('titulo', 'Sistema Integral de Información Departamental')

@section('contenido')
<section class="soft">
    <div class="wrap">
        <p class="tipo">Sistema Integral de Información Departamental</p>
        <h1>Indicadores del Meta</h1>
        <p>Consulte el catálogo institucional y los indicadores territoriales publicados.</p>
        <form method="get" class="card" style="display:flex;gap:12px;margin:24px 0;padding:16px">
            <label style="flex:1">Buscar por código o nombre<input name="q" value="{{ request('q') }}" type="search" style="width:100%;padding:10px;margin-top:6px"></label>
            <button class="btn" type="submit">Buscar</button>
        </form>
        <div class="grid">
            @forelse($publicaciones as $publicacion)
                @php($indicador = $publicacion->publicable)
                <article class="card ind">
                    @if($publicacion->imageUrl())<img src="{{ $publicacion->imageUrl() }}" alt="" loading="lazy" style="width:100%;height:180px;object-fit:cover;border-radius:12px;margin-bottom:18px">@endif
                    <span class="tipo">{{ $indicador instanceof \App\Models\SiidIndicador ? ($indicador->eje?->nombre ?? 'Indicador SIID') : 'Indicador territorial' }}</span>
                    @if($publicacion->destacado)<span class="badge-real">Destacado</span>@endif
                    <h2 style="font-size:1.15rem">{{ $publicacion->etiqueta ?: $indicador->etiqueta() }}</h2>
                    <p>{{ $publicacion->descripcion_publica ?: ($indicador->descripcion ?? $indicador->definicion ?? 'Consulte la ficha institucional del indicador.') }}</p>
                    <a class="btn out" href="{{ route('micrositio.siid.show', $publicacion) }}">Consultar indicador</a>
                </article>
            @empty
                <p>No hay indicadores publicados que coincidan con la búsqueda.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
