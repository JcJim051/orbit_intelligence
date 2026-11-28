@php
    $seriesPayload = [
        'unidad' => $indicador->unidad_medida,
        'series' => $territorialSeries,
    ];
@endphp

<section class="territorial-series card" data-territorial-series>
    <script type="application/json" data-territorial-series-payload>{!! json_encode($seriesPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <div class="territorial-series-heading">
        <div>
            <p class="tipo">Serie territorial</p>
            <h2>Evolución del indicador</h2>
            <p>La visualización utiliza exclusivamente datos aprobados por el equipo de información.</p>
        </div>
        <span class="badge-real" data-territorial-series-count>{{ count($territorialSeries) }} serie(s)</span>
    </div>

    @if($territorialSeries === [])
        <div class="territorial-series-empty">Serie todavía no disponible.</div>
    @else
        <div class="territorial-series-filters" aria-label="Filtros de la serie territorial">
            <label>Territorio<select data-territorial-filter="territorio"></select></label>
            <label>Tipo de desagregación<select data-territorial-filter="tipo"></select></label>
            <label>Desagregación<select data-territorial-filter="valor"></select></label>
        </div>

        <p class="territorial-series-summary" data-territorial-series-summary aria-live="polite"></p>
        <div class="territorial-chart-wrap">
            <svg data-territorial-chart class="territorial-chart" viewBox="0 0 900 320" role="img" aria-label="Evolución histórica del indicador"></svg>
        </div>

        <div class="territorial-table-wrap">
            <table class="territorial-table">
                <thead><tr><th>Periodo</th><th>Fecha de corte</th><th>Territorio</th><th>Desagregación</th><th>Valor</th></tr></thead>
                <tbody data-territorial-table-body></tbody>
            </table>
        </div>
    @endif
</section>
