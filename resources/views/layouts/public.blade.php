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
        <nav aria-label="Navegación principal"><a href="{{ route('banks.create') }}" @if(request()->routeIs('banks.create')) aria-current="page" @endif>Solicitud de banco</a><a href="{{ route('banks.status.create') }}" @if(request()->routeIs('banks.status.create')) aria-current="page" @endif>Consultar estado</a></nav>
    </div>
</header>
<main id="contenido" tabindex="-1">@yield('content')</main>
<footer class="foot bank-footer" aria-labelledby="footer-entidad">
    <div class="wrap bank-footer-content">
        <div class="footer-marks">
            <a href="https://www.gov.co/home/" title="Ir al portal GOV.CO"><img src="{{ asset('img/micrositio/img/logo_govco.png') }}" alt="GOV.CO"></a>
            <img src="{{ asset('img/micrositio/img/marca_colombia.png') }}" alt="Marca Colombia">
        </div>
        <div class="bank-footer-entity">
            <h2 id="footer-entidad">Departamento Administrativo de Planeación</h2>
            <p>Gerencia de Información y Estudios Económicos</p>
            <p>Gobernación del Meta · Solicitudes de banco</p>
        </div>
        <nav class="bank-footer-links" aria-label="Información institucional">
            <a href="https://meta.gov.co/">Portal institucional</a>
        </nav>
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
