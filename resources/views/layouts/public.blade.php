<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Solicitud de banco') — Gobernación del Meta</title>
    <link rel="icon" type="image/png" href="{{ asset('img/micrositio/favicon-gobernacion-meta.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/banks.css', 'resources/js/banks.js'])
</head>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido principal</a>
<div class="govco"><a href="https://www.gov.co/home/" aria-label="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a></div>
<header class="top" tabindex="-1">
    <div class="wrap">
        <a href="{{ route('banks.create') }}" class="brand"><img src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="Gobernación del Meta"></a>
        <nav aria-label="Navegación principal"><a href="{{ route('banks.create') }}" @if(request()->routeIs('banks.create')) aria-current="page" @endif>Solicitud de banco</a><a href="{{ route('banks.status.create') }}" @if(request()->routeIs('banks.status.create')) aria-current="page" @endif>Consultar estado</a><a href="https://meta.gov.co/">meta.gov.co</a></nav>
    </div>
</header>
<main id="contenido" tabindex="-1">@yield('content')</main>
<footer class="foot" aria-labelledby="footer-entidad">
    <div class="wrap footer-portal">
        <div class="footer-identity">
            <img class="footer-entity-logo" src="{{ asset('img/micrositio/img/logo_blanco.png') }}" alt="Gobernación del Meta">
            <div class="footer-marks">
                <a href="https://www.gov.co/home/" title="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a>
                <img src="{{ asset('img/micrositio/img/marca_colombia.png') }}" alt="Marca Colombia">
            </div>
            <nav class="footer-links" aria-label="Redes sociales de la Gobernación">
                <h3>Síguenos</h3>
                <a href="https://twitter.com/gobmeta">X · @gobmeta</a>
                <a href="https://facebook.com/gobmeta">Facebook · Gobernación del Meta</a>
                <a href="https://instagram.com/gobernaciondelmeta">Instagram · @gobernaciondelmeta</a>
            </nav>
        </div>
        <div class="footer-location">
            <h2 id="footer-entidad">Gerencia de Información y Estudios Económicos</h2>
            <h3>Gobernación del Departamento del Meta</h3>
            <p>Carrera 33 # 38-45 El Centro – Plazoleta Los Libertadores, Villavicencio, Meta<br>Villavicencio · Meta, Colombia</p>
            <nav class="footer-links" aria-label="Atención presencial">
                <a href="https://meta.gov.co/pagina/espacios-fisicos-de-contacto-con-la-entidad/61">Espacios físicos de contacto con la entidad</a>
            </nav>
            <p>Horario de atención: Lunes a viernes de 8:00 a.m. a 11:30 a.m. y de 2:00 p.m. a 5:30 p.m.</p>
        </div>
        <div class="footer-contact">
            <h2>Contacto</h2>
            <p>Conmutador: +57 608 681 85 00<br>Línea gratuita: 01 8000 129 202<br>Línea anticorrupción: +57 608 6818503</p>
            <p>Correo institucional:<br><a href="mailto:gobernaciondelmeta@meta.gov.co">gobernaciondelmeta@meta.gov.co</a></p>
            <p>Notificaciones judiciales:<br><a href="mailto:notificacionesjudiciales@meta.gov.co">notificacionesjudiciales@meta.gov.co</a></p>
            <nav class="footer-links" aria-label="Información institucional">
                <a href="https://meta.gov.co/transparencia">Transparencia y acceso a la información pública</a>
                <a href="https://meta.gov.co/pagina/politicas-de-seguridad-de-la-informacion-del-sitio-web-y-proteccion-de-datos-personales/23">Políticas de seguridad de la información y protección de datos personales</a>
                <a href="https://meta.gov.co/mapa-del-sitio">Mapa del sitio de la Gobernación</a>
                <a href="https://meta.gov.co/">Portal de la Gobernación del Meta</a>
            </nav>
        </div>
    </div>
</footer>
<div class="a11y" role="group" aria-label="Opciones de accesibilidad">
    <button type="button" data-accessibility="contrast" aria-label="Contraste" aria-pressed="false"><span class="a11y-label">Contraste</span><span class="a11y-icon" aria-hidden="true">◐</span></button>
    <button type="button" data-accessibility="decrease" aria-label="Reducir letra"><span class="a11y-label">Reducir letra</span><span class="a11y-icon" aria-hidden="true">A⁻</span></button>
    <button type="button" data-accessibility="increase" aria-label="Aumentar letra"><span class="a11y-label">Aumentar letra</span><span class="a11y-icon" aria-hidden="true">A⁺</span></button>
</div>
<button type="button" class="back-to-top" aria-label="Volver arriba" hidden><span class="back-to-top-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 15 8-8 8 8" /></svg></span><span class="back-to-top-label" aria-hidden="true">Volver<br>arriba</span></button>
</body>
</html>
