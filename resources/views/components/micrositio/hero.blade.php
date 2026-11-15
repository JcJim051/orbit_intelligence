@props(['hero', 'cifras', 'mapa', 'preview' => false])
<section @class(['hero', 'con-foto' => $hero['usar_imagen_fondo'] && $hero['banner_url']]) @if ($hero['usar_imagen_fondo'] && $hero['banner_url']) style="background-image: linear-gradient(rgba(248,250,255,.88), rgba(255,255,255,.92)), url('{{ $hero['banner_url'] }}')" @endif>
    <div @class(['wrap', 'sin-mapa' => empty($mapa['mostrar_bloque'])])>
        <div>
            <div class="eyebrow">{{ $hero['eyebrow'] }}</div>
            <h1>{{ $hero['titulo'] ?: 'Gerencia de Información y Estudios Económicos' }}</h1>
            <p class="lead">{{ $hero['subtitulo'] }}</p>
            <div class="counters">
                @foreach ($cifras as $cifra)
                    <x-micrositio.contador :cifra="$cifra" :preview="$preview" />
                @endforeach
            </div>
            @if ($hero['fuentes_linea'])
                <div class="sources">{{ $hero['fuentes_linea'] }}</div>
            @endif
            <div class="pills">
                @foreach ($hero['pildoras'] as $pildora)
                    <a @class(['pill', 'primary' => $pildora['primaria']]) href="{{ $pildora['url'] }}">{{ $pildora['texto'] }}</a>
                @endforeach
            </div>
        </div>
        @if (! empty($mapa['mostrar_bloque']))
            <x-micrositio.mapa-inicio :mapa="$mapa" :preview="$preview" />
        @endif
    </div>
</section>
