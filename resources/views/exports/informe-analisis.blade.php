<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 13px; margin: 16px 0 6px; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #cbd5e1; padding: 3px 4px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        .pie { margin-top: 6px; color: #334155; }
        .aviso { margin-top: 6px; }
    </style>
</head>
<body>
    <p>Gobernación del Meta</p>
    <h1>Gerencia de Información y Estudios Económicos</h1>
    <p>Informe de análisis de área</p>
    <p>Fecha del análisis: {{ $fecha }}</p>
    <p>Usuario: {{ $usuario }}</p>
    <p>Municipios: {{ $municipios === [] ? 'Ningún municipio del catálogo intersecta el polígono.' : implode('; ', $municipios) }}</p>

    <h2>Mapa</h2>
    @if ($mapa !== '')
        {!! $mapa !!}
        <p>Polígono analizado en verde, parte fuera del Meta en naranja y cruces de las capas. El fondo gris es el límite departamental del Meta.</p>
    @else
        <p>No hay geometría para dibujar el mapa.</p>
    @endif

    <h2>Área</h2>
    <p>Área del polígono: {{ $area_total }} ha</p>
    <p>Área analizada: {{ $area_analizada }} ha</p>
    <p>Fuera del Meta: {{ $ha_fuera }} ha ({{ $pct_fuera }} %)</p>
    @if ($nota_fuera)
        <p class="aviso">{{ $nota_fuera }}</p>
    @endif

    @foreach ($capas as $capa)
        <h2>{{ $capa['nombre'] }}</h2>
        @if (! $capa['disponible'])
            <p>fuente no disponible</p>
        @else
            <p>{{ $capa['conteo'] }} entidades</p>
            @if ($capa['area_ha'] !== null)
                <p>{{ $capa['area_ha'] }} ha @if ($capa['porcentaje'] !== null)({{ $capa['porcentaje'] }} %)@endif</p>
            @endif
            @if ($capa['columnas'] !== [])
                <table>
                    <thead>
                        <tr>
                            @foreach ($capa['columnas'] as $columna)
                                <th>{{ $columna }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($capa['filas'] as $fila)
                            <tr>
                                @foreach ($fila as $celda)
                                    <td>{{ $celda }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($capa['columnas']) }}">Sin entidades en el polígono.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($capa['recorte'])
                    <p>La tabla muestra las primeras entidades. El conteo de arriba es el total.</p>
                @endif
            @endif
            @if ($capa['avaluo'])
                @if ($capa['avaluo']['incluido'])
                    <p>Avalúo ({{ $capa['avaluo']['campo'] }}): total {{ $capa['avaluo']['total'] }}</p>
                    @if ($capa['avaluo']['por_predio'])
                        <p>Promedio por predio: {{ $capa['avaluo']['por_predio'] }}</p>
                    @endif
                    @if ($capa['avaluo']['por_ha'])
                        <p>Promedio por ha: {{ $capa['avaluo']['por_ha'] }}</p>
                    @endif
                @else
                    <p>{{ $capa['avaluo']['texto'] }}</p>
                @endif
            @endif
        @endif
        <div class="pie">
            <p>Fuente: {{ $capa['fuente'] }}</p>
            <p>URL: {{ $capa['url'] }}</p>
            <p>Licencia: {{ $capa['licencia'] }}</p>
            <p>Fecha de corte: {{ $capa['fecha_corte'] }}</p>
            <p>Fecha de consulta: {{ $capa['consultado'] }}</p>
            <p>Origen: {{ $capa['origen'] }}</p>
            <p>Vigencia: {{ $capa['vigencia'] }}</p>
        </div>
    @endforeach

    <h2>Resumen de alertas</h2>
    @foreach ($alertas as $alerta)
        <p>{{ $alerta['titulo'] }}: {{ $alerta['texto'] }}</p>
    @endforeach
</body>
</html>
