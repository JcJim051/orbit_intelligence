@php
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
    <title>@yield('titulo', $contenido['hero']['titulo'] ?: 'Gerencia de Información y Estudios Económicos') | Gobernación del Meta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/micrositio.css', 'resources/js/micrositio.js'])
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="govco"><a href="https://www.gov.co/" target="_blank" rel="noopener"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a></div>
@if ($contenido['preview'])
    <div class="preview-banner">VISTA PREVIA DEL BORRADOR — no publicado</div>
@endif
<header class="top">
    <div class="wrap">
        <a href="{{ $enlace(route('micrositio.inicio', [], false)) }}" class="brand">
            <img src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="Gobernación del Meta">
            <span><b>Información y Estudios Económicos</b><small>Gobernación del Meta</small></span>
        </a>
        <nav>
            @foreach ($contenido['menu'] as $item)
                <a href="{{ $enlace($item['url']) }}">{{ $item['texto'] }}</a>
            @endforeach
        </nav>
    </div>
</header>
<main id="contenido">
    @yield('contenido')
</main>
<div class="a11y" aria-label="Accesibilidad">
    <button type="button" onclick="document.body.classList.toggle('hc')" title="Alto contraste">◐</button>
    <button type="button" onclick="window.cambiarTexto(1)" title="Aumentar texto">A+</button>
    <button type="button" onclick="window.cambiarTexto(-1)" title="Disminuir texto">A−</button>
</div>
<footer class="foot">
    <div class="wrap cols">
        <div>
            <h4>Gobernación del Meta · Gerencia de Información y Estudios Económicos</h4>
            <p>{{ $contenido['contacto']['direccion'] }}<br>Horario: {{ $contenido['contacto']['horario'] }}</p>
            <p>Conmutador: {{ $contenido['contacto']['conmutador'] }}<br>Línea gratuita: {{ $contenido['contacto']['linea_gratuita'] }}<br>Línea anticorrupción: {{ $contenido['contacto']['anticorrupcion'] }}</p>
            <p>Correo: {{ $contenido['contacto']['correo'] }}<br>Notificaciones judiciales: {{ $contenido['contacto']['judiciales'] }}</p>
        </div>
        <div>
            <h4>Enlaces</h4>
            <p>
                <a href="https://meta.gov.co/transparencia">Transparencia y acceso a la información pública</a><br>
                <a href="https://meta.gov.co/pagina/politicas-de-seguridad-de-la-informacion-del-sitio-web-y-proteccion-de-datos-personales/23">Política de tratamiento de datos personales</a><br>
                <a href="https://meta.gov.co">Términos y condiciones</a><br>
                <a href="https://meta.gov.co">PQRSD</a><br>
                <a href="{{ route('micrositio.inicio') }}">Mapa del sitio</a>
            </p>
        </div>
    </div>
    <div class="wrap logos">
        <img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO">
        <img src="{{ asset('img/micrositio/img/marca_colombia.png') }}" alt="Marca Colombia">
        <span>Contenido administrado desde el CMS del SIID</span>
    </div>
</footer>
<script>
    window.tamanoTexto = 100;
    window.cambiarTexto = (delta) => {
        window.tamanoTexto = Math.min(140, Math.max(80, window.tamanoTexto + delta * 10));
        document.documentElement.style.fontSize = window.tamanoTexto + '%';
    };
</script>
</body>
</html>
