@php
    $rutasNavegacion = [route('micrositio.inicio', [], false), route('micrositio.indicadores', [], false), route('micrositio.politicas', [], false), route('micrositio.publicaciones', [], false)];
    $enlace = function (string $url) use ($contenido): string {
        if (! ($contenido['preview'] ?? false) || ! str_starts_with($url, '/')) {
            return $url;
        }

        [$ruta, $ancla] = array_pad(explode('#', $url, 2), 2, null);
        $separador = str_contains($ruta, '?') ? '&' : '?';
        $ruta .= $separador.'preview=1';

        return $ancla ? $ruta.'#'.$ancla : $ruta;
    };
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', $contenido['hero']['titulo'] ?: 'Gerencia de Información y Estudios Económicos') </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://datastudio.google.com" crossorigin>
    <link rel="preconnect" href="https://lookerstudio.google.com" crossorigin>
    <link rel="dns-prefetch" href="//datastudio.google.com">
    <link rel="dns-prefetch" href="//lookerstudio.google.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/micrositio.css', 'resources/js/micrositio.js'])
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>
<div class="govco"><a href="https://www.gov.co/home/" aria-label="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a></div>
@if ($contenido['preview'])
    <div class="preview-banner">VISTA PREVIA DEL BORRADOR — no publicado</div>
@endif
<header class="top" tabindex="-1">
    <div class="wrap">
        <a href="{{ $enlace(route('micrositio.inicio', [], false)) }}" class="brand">
            <img class="brand-logo-light" src="{{ asset('img/micrositio/img/logo_LogoGoberMeta1.png') }}" alt="Gobernación del Meta"><img class="brand-logo-dark" src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="Gobernación del Meta">
            <span><b>Información y Estudios Económicos</b></span>
        </a>
        <button class="menu-toggle" type="button" aria-controls="menu-principal" aria-expanded="false" hidden><span aria-hidden="true">☰</span> Menú</button>
        <nav id="menu-principal" data-micrositio-nav data-rutas='@json($rutasNavegacion)' aria-label="Navegación principal">
            <a href="{{ $enlace(route('micrositio.inicio', [], false)) }}" @if(request()->routeIs('micrositio.inicio')) aria-current="page" @endif>Inicio</a>
            <a href="{{ $enlace(route('micrositio.politicas', [], false)) }}" @if(request()->routeIs('micrositio.politicas')) aria-current="page" @endif>Políticas Públicas</a>
            <a href="{{ $enlace(route('micrositio.publicaciones', [], false)) }}" @if(request()->routeIs('micrositio.publicaciones')) aria-current="page" @endif>Publicaciones</a>
            @foreach ($contenido['menu'] as $item)
                @continue(mb_strtolower($item['texto']) === 'publicaciones')
                @continue(in_array(rtrim($item['url'], '/'), [route('micrositio.inicio', [], false), route('micrositio.politicas', [], false), route('micrositio.inicio'), route('micrositio.politicas')], true))
                <a href="{{ $enlace($item['url']) }}" @if(rtrim(parse_url($item['url'], PHP_URL_PATH) ?? '', '/') === rtrim(request()->getPathInfo(), '/') && !parse_url($item['url'], PHP_URL_FRAGMENT)) aria-current="page" @endif>{{ $item['texto'] }}</a>
            @endforeach
        </nav>
    </div>
</header>
<div class="navigation-feedback" data-navigation-feedback hidden>
    <span class="navigation-spinner" aria-hidden="true"></span>
    <span role="status" aria-live="polite" aria-atomic="true" data-navigation-message></span>
    <button type="button" class="btn out" data-navigation-retry hidden>Reintentar</button>
</div>
<main id="contenido" tabindex="-1">
    @if (! request()->routeIs('micrositio.inicio'))
        @php
            $migas = [];
            if (request()->routeIs('micrositio.publicaciones')) {
                $migas[] = ['nombre' => 'Publicaciones', 'url' => null];
            } elseif (request()->routeIs('micrositio.politicas')) {
                $migas[] = ['nombre' => 'Políticas Públicas', 'url' => isset($seleccionada) ? route('micrositio.politicas', [], false) : null];
                if (isset($seleccionada)) {
                    $migas[] = ['nombre' => $seleccionada['nombre'], 'url' => null];
                }
            } elseif (request()->routeIs('micrositio.indicadores')) {
                $migas[] = ['nombre' => 'Indicadores', 'url' => request()->filled('i') ? route('micrositio.indicadores', [], false) : null];
                if (request()->filled('i') && isset($seleccionado)) {
                    $migas[] = ['nombre' => $seleccionado['nombre'], 'url' => null];
                }
            }
        @endphp
        @if ($migas)
            <nav class="wrap breadcrumbs" aria-label="Ruta de navegación">
                <ol>
                    <li><a href="{{ $enlace(route('micrositio.inicio', [], false)) }}">Inicio</a></li>
                    @foreach ($migas as $miga)
                        <li>
                            <span class="breadcrumb-separator" aria-hidden="true">›</span>
                            @if ($miga['url'])
                                <a href="{{ $enlace($miga['url']) }}">{{ $miga['nombre'] }}</a>
                            @else
                                <span aria-current="page">{{ $miga['nombre'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
    @endif
    @yield('contenido')
</main>
<div class="a11y" role="group" aria-label="Opciones de accesibilidad">
    <button type="button" data-accessibility="contrast" aria-label="Contraste" aria-pressed="false"><span class="a11y-label">Contraste</span><span class="a11y-icon" aria-hidden="true">◐</span></button>
    <button type="button" data-accessibility="decrease" aria-label="Reducir letra"><span class="a11y-label">Reducir letra</span><span class="a11y-icon" aria-hidden="true">A⁻</span></button>
    <button type="button" data-accessibility="increase" aria-label="Aumentar letra"><span class="a11y-label">Aumentar letra</span><span class="a11y-icon" aria-hidden="true">A⁺</span></button>
</div>
<footer class="foot" aria-labelledby="footer-entidad">
    <div class="wrap footer-portal">
        <div class="footer-identity">
            <img class="footer-entity-logo" src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="Gobernación del Meta">
            <div class="footer-marks">
                <a href="https://www.gov.co/home/" title="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a>
                <img src="{{ asset('img/micrositio/img/marca_colombia.png') }}" alt="Marca Colombia">
            </div>
        </div>
        <div class="footer-location">
            <h2 id="footer-entidad">Gerencia de Información y Estudios Económicos</h2>
            <h3>Sede principal</h3>
            <p>{{ $contenido['contacto']['direccion'] }}</p>
            <p>Horario de atención: {{ $contenido['contacto']['horario'] }}</p>
        </div>
        <div class="footer-contact">
            <h2>Contacto</h2>
            <p>Conmutador: {{ $contenido['contacto']['conmutador'] }}<br>Línea gratuita: {{ $contenido['contacto']['linea_gratuita'] }}<br>Línea anticorrupción: {{ $contenido['contacto']['anticorrupcion'] }}</p>
            <p>Correo institucional:<br><a href="mailto:{{ $contenido['contacto']['correo'] }}">{{ $contenido['contacto']['correo'] }}</a></p>
            <p>Notificaciones judiciales:<br><a href="mailto:{{ $contenido['contacto']['judiciales'] }}">{{ $contenido['contacto']['judiciales'] }}</a></p>
            <nav class="footer-links" aria-label="Información institucional">
                <a href="https://meta.gov.co/transparencia">Transparencia y acceso a la información pública</a>
                <a href="https://meta.gov.co/pagina/politicas-de-seguridad-de-la-informacion-del-sitio-web-y-proteccion-de-datos-personales/23">Política de tratamiento de datos personales</a>
                <a href="https://meta.gov.co/">Portal de la Gobernación del Meta</a>
            </nav>
        </div>
    </div>
</footer>
<button type="button" class="back-to-top" aria-label="Volver arriba" hidden><span class="back-to-top-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="m4 15 8-8 8 8" /></svg></span><span class="back-to-top-label" aria-hidden="true">Volver<br>arriba</span></button>

</body>
</html>
