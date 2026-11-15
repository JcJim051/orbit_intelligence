@php
    $rutasNavegacion = [route('micrositio.inicio', [], false), route('micrositio.indicadores', [], false), route('micrositio.politicas', [], false)];
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/micrositio.css', 'resources/js/micrositio.js'])
</head>
<body @class(['pagina-inicio' => request()->routeIs('micrositio.inicio')]) data-preview="{{ ($contenido['preview'] ?? false) ? '1' : '0' }}">
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>
<div class="govco"><a href="https://www.gov.co/home/" aria-label="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a></div>
@if ($contenido['preview'])
    <div class="preview-banner">VISTA PREVIA DEL BORRADOR — no publicado</div>
@endif
<header class="top" tabindex="-1">
    <div class="wrap">
        <a href="{{ $enlace(route('micrositio.inicio', [], false)) }}" class="brand">
            <img class="brand-logo-light" src="{{ asset('img/micrositio/img/logo_LogoGoberMeta1.png') }}" alt="Gobernación del Meta"><img class="brand-logo-dark" src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="">
            <span class="sep" aria-hidden="true"></span>
            <span><b>Información y Estudios Económicos</b><small>SIID · Gobernación del Meta</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-controls="menu-principal" aria-expanded="false" hidden><span aria-hidden="true">☰</span> Menú</button>
        <nav id="menu-principal" data-micrositio-nav data-rutas='@json($rutasNavegacion)' aria-label="Navegación principal">
            <a href="{{ $enlace(route('micrositio.inicio', [], false)) }}" @if(request()->routeIs('micrositio.inicio')) aria-current="page" @endif>Inicio</a>
            <a href="{{ $enlace(route('micrositio.politicas', [], false)) }}" @if(request()->routeIs('micrositio.politicas')) aria-current="page" @endif>Políticas Públicas</a>
            @foreach ($contenido['menu'] as $item)
                @continue(in_array(rtrim($item['url'], '/'), [route('micrositio.inicio', [], false), route('micrositio.politicas', [], false), route('micrositio.inicio'), route('micrositio.politicas')], true))
                <a href="{{ $enlace($item['url']) }}" @if(rtrim(parse_url($item['url'], PHP_URL_PATH) ?? '', '/') === rtrim(request()->getPathInfo(), '/') && !parse_url($item['url'], PHP_URL_FRAGMENT)) aria-current="page" @endif>{{ $item['texto'] }}</a>
            @endforeach
            <form class="search" method="get" action="{{ $enlace(route('micrositio.indicadores', [], false)) }}" role="search">
                <label class="sr-only" for="buscar-micrositio">Buscar indicadores</label>
                <input id="buscar-micrositio" type="search" name="q" placeholder="Buscar">
                <button type="submit" aria-label="Buscar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
            </form>
        </nav>
    </div>
</header>
<main id="contenido" tabindex="-1">
    @yield('contenido')
</main>
<div class="a11y" role="group" aria-label="Opciones de accesibilidad">
    <button type="button" data-accessibility="contrast" aria-label="Contraste" aria-pressed="false"><span class="a11y-label">Contraste</span><span class="a11y-icon" aria-hidden="true">◐</span></button>
    <button type="button" data-accessibility="decrease" aria-label="Reducir letra"><span class="a11y-label">Reducir letra</span><span class="a11y-icon" aria-hidden="true">A⁻</span></button>
    <button type="button" data-accessibility="increase" aria-label="Aumentar letra"><span class="a11y-label">Aumentar letra</span><span class="a11y-icon" aria-hidden="true">A⁺</span></button>
</div>
<footer class="foot" aria-labelledby="footer-entidad">
    <div class="wrap">
        <div class="footer-portal">
            <div class="footer-identity">
                <img class="footer-entity-logo" src="{{ asset('img/micrositio/img/logo_LogoGoberMeta1.png') }}" alt="Gobernación del Meta">
                <h2 id="footer-entidad">Gerencia de Información y Estudios Económicos</h2>
                <h3>Sede principal</h3>
                <p>{{ $contenido['contacto']['direccion'] }}</p>
                <p>Horario de atención: {{ $contenido['contacto']['horario'] }}</p>
            </div>
            <div>
                <h2>Explorar</h2>
                <nav class="footer-links" aria-label="Explorar el micrositio">
                    <a href="{{ $enlace(route('micrositio.indicadores', [], false)) }}">Indicadores</a>
                    <a href="{{ $enlace(route('micrositio.politicas', [], false)) }}">Políticas públicas</a>
                    <a href="{{ $enlace(route('micrositio.inicio', [], false)) }}#publicaciones">Publicaciones</a>
                </nav>
            </div>
            <div>
                <h2>Transparencia</h2>
                <nav class="footer-links" aria-label="Información institucional">
                    <a href="https://meta.gov.co/transparencia">Transparencia y acceso a la información pública</a>
                    <a href="https://meta.gov.co/pagina/politicas-de-seguridad-de-la-informacion-del-sitio-web-y-proteccion-de-datos-personales/23">Política de tratamiento de datos personales</a>
                    <a href="https://meta.gov.co/">Portal de la Gobernación del Meta</a>
                </nav>
            </div>
            <div class="footer-contact">
                <h2>Contacto</h2>
                <p>Conmutador: {{ $contenido['contacto']['conmutador'] }}</p>
                <p>Línea gratuita: {{ $contenido['contacto']['linea_gratuita'] }}</p>
                <p>Línea anticorrupción: {{ $contenido['contacto']['anticorrupcion'] }}</p>
                <p>Correo institucional: <a href="mailto:{{ $contenido['contacto']['correo'] }}">{{ $contenido['contacto']['correo'] }}</a></p>
                <p>Notificaciones judiciales: <a href="mailto:{{ $contenido['contacto']['judiciales'] }}">{{ $contenido['contacto']['judiciales'] }}</a></p>
            </div>
        </div>
        <div class="legal">
            <span>© {{ now()->year }} Gobernación del Meta — Sistema Integral de Información Departamental</span>
            <span class="footer-marks">
                <a href="https://www.gov.co/home/" title="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a>
                <img src="{{ asset('img/micrositio/img/marca_colombia.png') }}" alt="Marca Colombia">
            </span>
        </div>
    </div>
</footer>
<x-micrositio.modal />
<button type="button" class="back-to-top" aria-label="Volver arriba" hidden><span class="back-to-top-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="m4 15 8-8 8 8" /></svg></span><span class="back-to-top-label" aria-hidden="true">Volver<br>arriba</span></button>

</body>
</html>
