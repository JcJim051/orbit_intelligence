@extends('micrositio.layout')
@section('titulo', 'Políticas Públicas')
@section('contenido')
@php
    $ficha = static function (array $politica): array {
        preg_match_all('/\b(?:19|20)\d{2}\b/', (string) ($politica['vigencia'] ?? ''), $coincidencias);
        $inicio = isset($coincidencias[0][0]) ? (int) $coincidencias[0][0] : null;
        $fin = isset($coincidencias[0][1]) ? (int) $coincidencias[0][1] : null;
        $valida = $inicio !== null && $fin !== null && $fin >= $inicio;
        $actual = now()->year;
        $total = $valida ? $fin - $inicio + 1 : 0;
        $transcurridos = $valida ? max(0, min($total, $actual - $inicio + 1)) : 0;
        $estado = ! $valida ? 'Sin vigencia registrada' : ($actual < $inicio ? 'Próxima' : ($actual > $fin ? 'Finalizada' : 'Vigente'));
        return [
            'sector' => $politica['sector'] ?? 'Políticas departamentales',
            'fecha' => $politica['fecha_adopcion'] ?? 'No registrada',
            'inicio' => $inicio, 'fin' => $fin, 'valida' => $valida,
            'total' => $total, 'transcurridos' => $transcurridos,
            'porcentaje' => $total > 0 ? (int) round($transcurridos / $total * 100) : 0,
            'estado' => $estado,
            'por_vencer' => $valida && $estado === 'Vigente' && $fin - $actual <= 2,
        ];
    };
    $seleccionada ??= $politicas[0] ?? null;
    $categorias = [
        'norma' => 'Ordenanza y actos administrativos',
        'tecnico' => 'Documento técnico',
        'seguimiento' => 'Informes de seguimiento',
        'infografia' => 'Infografías',
    ];
    $grupos = array_fill_keys(array_keys($categorias), []);
    if ($seleccionada) {
        $datos = $ficha($seleccionada);
        foreach ($seleccionada['documentos'] ?? [] as $indice => $archivo) {
            $nombre = $seleccionada['nombres_documentos'][$archivo] ?? 'Documento '.($indice + 1);
            $normalizado = mb_strtolower($nombre);
            $categoria = match (true) {
                str_contains($normalizado, 'ordenanza'), str_contains($normalizado, 'acto administrativo') => 'norma',
                str_contains($normalizado, 'informe'), str_contains($normalizado, 'seguimiento') => 'seguimiento',
                str_contains($normalizado, 'infograf') => 'infografia',
                default => 'tecnico',
            };
            $grupos[$categoria][] = compact('indice', 'archivo', 'nombre');
        }
    }
    $carpetaInicial = collect($grupos)->search(fn ($archivos) => count($archivos) > 0) ?: 'norma';
    $sectores = collect($politicas)->map(fn ($politica) => $ficha($politica)['sector'])->unique()->sort();
@endphp
<section class="wrap policy-workspace" data-policy-workspace>
    <aside class="policy-sidebar" aria-label="Buscar y seleccionar políticas públicas">
        <h2>Políticas públicas</h2>
        <label for="policy-query">Buscar política</label>
        <input id="policy-query" type="search" placeholder="Nombre o palabra clave" aria-describedby="policy-search-help" data-policy-query>
        <p class="policy-search-help" id="policy-search-help">También puedes buscar por norma o dependencia.</p>
        <label for="policy-sector">Sector</label>
        <select id="policy-sector" data-policy-sector>
            <option value="">Todos los sectores</option>
            @foreach ($sectores as $sector)
                <option value="{{ $sector }}">{{ $sector }}</option>
            @endforeach
        </select>
        <fieldset class="policy-status-filter">
            <legend>Estado</legend>
            @foreach (['todas' => 'Todas', 'vigentes' => 'Vigentes', 'por-vencer' => 'Por vencer'] as $valor => $texto)
                <label for="policy-state-{{ $valor }}"><input id="policy-state-{{ $valor }}" type="radio" name="policy-state" value="{{ $valor }}" @checked($loop->first)><span>{{ $texto }}</span></label>
            @endforeach
        </fieldset>
        <button type="button" class="policy-reset" data-policy-reset>Limpiar filtros</button>
        <p class="policy-result-count" data-policy-count role="status">{{ count($politicas) }} {{ count($politicas) === 1 ? 'política disponible' : 'políticas disponibles' }}</p>
        <nav class="policy-menu" aria-label="Listado de políticas">
            @foreach ($politicas as $politica)
                @php
                    $meta = $ficha($politica);
                @endphp
                <a href="{{ route('micrositio.politicas', ['politica' => $politica['slug']] + ($preview ? ['preview' => 1] : [])) }}"
                    data-policy-item data-policy-search="{{ $politica['nombre'].' '.($politica['ordenanza'] ?? '').' '.($politica['responsable'] ?? '') }}"
                    data-policy-sector-value="{{ $meta['sector'] }}" data-policy-current="{{ $meta['estado'] === 'Vigente' ? '1' : '0' }}" data-policy-expiring="{{ $meta['por_vencer'] ? '1' : '0' }}"
                    @if ($seleccionada && $seleccionada['slug'] === $politica['slug']) aria-current="page" @endif>
                    <strong>{{ $politica['nombre'] }}</strong>
                    <span>{{ $meta['sector'] }}</span>
                </a>
            @endforeach
        </nav>
        <p data-policy-empty hidden>No hay coincidencias. Cambia los filtros o prueba con otro nombre.</p>
        <details class="policy-about">
            <summary>¿Qué son las políticas públicas?</summary>
            <p>Son decisiones y acciones de las instituciones públicas, construidas con la participación de la ciudadanía, para atender necesidades colectivas y orientar el desarrollo del territorio.</p>
        </details>
    </aside>
    <article class="policy-detail">
        @if ($seleccionada)
            <header class="policy-intro">

                <div class="policy-eyebrow"><span>{{ $datos['sector'] }}</span><span class="policy-state">{{ $datos['estado'] }}</span></div>
                <div class="policy-heading-row">
                    <h1>{{ $seleccionada['nombre'] }}</h1>
                    <div class="policy-dashboard">
                        <button type="button" data-policy-dashboard aria-expanded="false" aria-controls="policy-dashboard-notice">Ver seguimiento </button>
                    </div>
                </div>
                <p id="policy-dashboard-notice" class="policy-dashboard-notice" hidden>Esta política aún no tiene un tablero de seguimiento configurado.</p>
                @if ($seleccionada['objetivo'])
                    <section class="policy-objective" aria-labelledby="policy-objective-heading">
                        <h2 id="policy-objective-heading">Objetivo</h2>
                        <p>{{ $seleccionada['objetivo'] }}</p>
                    </section>
                @else
                    <p class="policy-description">{{ $seleccionada['descripcion'] }}</p>
                @endif
                <dl class="policy-metadata">
                    <div><dt>Norma de adopción</dt><dd>{{ $seleccionada['ordenanza'] ?: 'No registrada' }}</dd></div>
                    <div><dt>Fecha de adopción</dt><dd>{{ $datos['fecha'] }}</dd></div>
                    <div><dt>Dependencia líder</dt><dd>{{ $seleccionada['responsable'] ?: 'No registrada' }}</dd></div>
                </dl>
            </header>
            <section class="policy-validity" aria-labelledby="policy-validity-heading">
                <h2 id="policy-validity-heading">Vigencia</h2>
                @if ($datos['valida'])
                    <div class="policy-years"><strong>{{ $datos['inicio'] }} – {{ $datos['fin'] }}</strong><span>{{ $datos['total'] }} años de vigencia</span></div>
                    <div class="policy-timeline">
                        <p>Hoy · {{ now()->year }}</p>
                        <div class="policy-progress" role="progressbar" aria-label="Tiempo de vigencia transcurrido" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $datos['porcentaje'] }}" aria-valuetext="{{ $datos['transcurridos'] }} de {{ $datos['total'] }} periodos anuales">
                            <span style="width: {{ $datos['porcentaje'] }}%"></span>
                        </div>
                        <div class="policy-ticks">
                            @foreach (array_unique([$datos['inicio'], (int) round(($datos['inicio'] + $datos['fin']) / 2), $datos['fin']]) as $anio)
                                <span>{{ $anio }}</span>
                            @endforeach
                        </div>
                    </div>
                    <p class="policy-time-caption">{{ $datos['transcurridos'] }} de {{ $datos['total'] }} periodos anuales · {{ $datos['porcentaje'] }} % de la vigencia transcurrida · {{ max(0, $datos['fin'] - now()->year) }} años restantes.</p>
                    <p class="policy-time-note">Cálculo por años calendario; no representa el avance de ejecución de la política.</p>
                @else
                    <p>{{ $seleccionada['vigencia'] ?: 'La vigencia aún no está registrada.' }}</p>
                @endif
            </section>
            <details class="policy-files">
                <summary>
                    <h2>Documentos de la política</h2>
                    <span class="policy-files-toggle"><span class="policy-files-open"></span><span class="policy-files-close"></span><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg></span>
                </summary>
                <div class="policy-files-body">
                <div class="policy-folders" aria-label="Categorías de documentos">
                    @foreach ($categorias as $clave => $nombre)
                        <button type="button" class="policy-folder" data-policy-folder="{{ $clave }}" aria-pressed="{{ $clave === $carpetaInicial ? 'true' : 'false' }}" aria-controls="policy-documents-panel">
                            <svg viewBox="0 0 32 28" aria-hidden="true" focusable="false"><path d="M3 5a2 2 0 0 1 2-2h7l4 4h11a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/></svg>
                            <span><strong>{{ $nombre }}</strong><span>{{ count($grupos[$clave]) }} {{ count($grupos[$clave]) === 1 ? 'archivo' : 'archivos' }}</span></span>
                        </button>
                    @endforeach
                </div>
                <div id="policy-documents-panel" class="policy-folder-content" aria-live="polite">
                    @foreach ($grupos as $clave => $archivos)
                        <div data-policy-folder-panel="{{ $clave }}" @if ($clave !== $carpetaInicial) hidden @endif>
                            <h3>{{ $categorias[$clave] }}</h3>
                            @forelse ($archivos as $documento)
                                @php
                                    $url = route('micrositio.politicas.documento', ['slug' => $seleccionada['slug'], 'indice' => $documento['indice']] + ($preview ? ['preview' => 1] : []));
                                    $pdf = strtolower(pathinfo($documento['archivo'], PATHINFO_EXTENSION)) === 'pdf';
                                @endphp
                                <div class="policy-document">
                                    <span class="document-format">{{ $pdf ? 'PDF' : strtoupper(pathinfo($documento['archivo'], PATHINFO_EXTENSION)) }}</span>
                                    <strong>{{ $documento['nombre'] }}</strong>
                                    <div class="document-actions">
                                        @if ($pdf)
                                            <button type="button" data-pdf-preview="{{ $url.(str_contains($url, '?') ? '&' : '?').'inline=1' }}" data-document-name="{{ $documento['nombre'] }}" data-download-url="{{ $url }}">Vista previa</button>
                                        @endif
                                        <a href="{{ $url }}" download>Descargar</a>
                                    </div>
                                </div>
                            @empty
                                <p>No hay archivos publicados en esta categoría.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
                </div>
            </details>
            @include('micrositio.pdf-viewer')
        @else
            <h1>Políticas públicas del Meta</h1>
            <p>Aún no hay políticas publicadas.</p>
        @endif
    </article>
</section>
@endsection
