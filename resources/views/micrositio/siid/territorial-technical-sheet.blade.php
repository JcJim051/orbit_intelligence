@php
    $periodicidad = \App\Models\IndicadorTerritorial::PERIODICIDADES[$indicador->periodicidad] ?? $indicador->periodicidad;
    $sentidos = ['aumentar' => 'Aumentar', 'disminuir' => 'Disminuir', 'mantener' => 'Mantener'];
    $sentido = $sentidos[$indicador->sentido_deseado] ?? $indicador->sentido_deseado;
    $dependencia = $indicador->dependencia?->nombre ?? $indicador->dependencia_fuente;
    $metaProducto = $indicador->metaProducto;
    $indicadorResultado = $indicador->indicadorResultado;
    $lineaBase = filled($indicador->linea_base_valor)
        ? rtrim(rtrim(number_format((float) $indicador->linea_base_valor, 6, ',', '.'), '0'), ',')
        : null;
@endphp

<section class="territorial-technical-sheet" aria-labelledby="territorial-technical-title">
    <div class="territorial-technical-heading">
        <div>
            <p class="tipo">Información metodológica</p>
            <h2 id="territorial-technical-title">Ficha técnica del indicador</h2>
            <p>Consulte la identificación, metodología, fuente, alcance y relación del indicador con el Plan de Desarrollo.</p>
        </div>

        @if($fichaDefinitivaPdfUrl)
            <aside class="territorial-definitive-file">
                <span class="territorial-definitive-file__icon" aria-hidden="true">PDF</span>
                <div>
                    <strong>Ficha técnica definitiva</strong>
                    <span>Documento oficial cargado para este indicador.</span>
                    <a href="{{ $fichaDefinitivaPdfUrl }}" target="_blank" rel="noopener">Abrir ficha definitiva</a>
                </div>
            </aside>
        @elseif($indicador->url_ficha)
            <aside class="territorial-definitive-file territorial-definitive-file--external">
                <span class="territorial-definitive-file__icon" aria-hidden="true">WEB</span>
                <div>
                    <strong>Ficha técnica externa</strong>
                    <span>El indicador todavía no tiene un PDF definitivo cargado.</span>
                    <a href="{{ $indicador->url_ficha }}" target="_blank" rel="noopener">Consultar ficha externa</a>
                </div>
            </aside>
        @endif
    </div>

    <div class="territorial-technical-grid">
        <article class="territorial-technical-card">
            <p class="tipo">Identificación</p>
            <h3>Datos generales</h3>
            <dl>
                @if($indicador->codigo)<div><dt>Código</dt><dd>{{ $indicador->codigo }}</dd></div>@endif
                @if($indicador->convencion)<div><dt>Convención o sigla</dt><dd>{{ $indicador->convencion }}</dd></div>@endif
                @if($indicador->definicion)<div class="is-wide"><dt>Definición</dt><dd>{{ $indicador->definicion }}</dd></div>@endif
                @if($indicador->objetivo)<div class="is-wide"><dt>Objetivo</dt><dd>{{ $indicador->objetivo }}</dd></div>@endif
                @if($indicador->area_tematica)<div><dt>Área temática</dt><dd>{{ $indicador->area_tematica }}</dd></div>@endif
                @if($indicador->tipo_indicador)<div><dt>Tipo de indicador</dt><dd>{{ $indicador->tipo_indicador }}</dd></div>@endif
            </dl>
        </article>

        <article class="territorial-technical-card">
            <p class="tipo">Medición</p>
            <h3>Cálculo e interpretación</h3>
            <dl>
                @if($indicador->unidad_medida)<div><dt>Unidad de medida</dt><dd>{{ $indicador->unidad_medida }}</dd></div>@endif
                @if($sentido)<div><dt>Sentido deseado</dt><dd>{{ $sentido }}</dd></div>@endif
                @if($periodicidad)<div><dt>Periodicidad</dt><dd>{{ $periodicidad }}</dd></div>@endif
                @if($indicador->periodicidad_original && $indicador->periodicidad_original !== $indicador->periodicidad)<div><dt>Periodicidad original</dt><dd>{{ $indicador->periodicidad_original }}</dd></div>@endif
                @if($lineaBase || $indicador->linea_base_periodo)<div><dt>Línea base</dt><dd>{{ $lineaBase ?? 'Sin valor' }}@if($indicador->linea_base_periodo) <span>({{ $indicador->linea_base_periodo }})</span>@endif</dd></div>@endif
                @if($indicador->formula_calculo)<div class="is-wide"><dt>Fórmula o regla de cálculo</dt><dd>{{ $indicador->formula_calculo }}</dd></div>@endif
                @if($indicador->variables_calculo)<div class="is-wide"><dt>Variables del cálculo</dt><dd>{{ $indicador->variables_calculo }}</dd></div>@endif
            </dl>
        </article>

        <article class="territorial-technical-card">
            <p class="tipo">Gestión del dato</p>
            <h3>Fuente, responsable y alcance</h3>
            <dl>
                @if($indicador->fuente_informacion)<div class="is-wide"><dt>Fuente de información</dt><dd>{{ $indicador->fuente_informacion }}</dd></div>@endif
                @if($indicador->responsable_informacion)<div class="is-wide"><dt>Responsable de la información</dt><dd>{{ $indicador->responsable_informacion }}</dd></div>@endif
                @if($dependencia)<div><dt>Dependencia responsable</dt><dd>{{ $dependencia }}</dd></div>@endif
                @if($indicador->cobertura_geografica)<div><dt>Cobertura geográfica</dt><dd>{{ $indicador->cobertura_geografica }}</dd></div>@endif
                @if($indicador->desagregaciones)<div class="is-wide"><dt>Desagregaciones</dt><dd>{{ $indicador->desagregaciones }}</dd></div>@endif
                @if($indicador->limitaciones)<div class="is-wide is-caution"><dt>Limitaciones</dt><dd>{{ $indicador->limitaciones }}</dd></div>@endif
            </dl>
        </article>

        @if($metaProducto || $indicadorResultado)
            <article class="territorial-technical-card">
                <p class="tipo">Planeación</p>
                <h3>Relación con el Plan de Desarrollo</h3>
                <dl>
                    @if($metaProducto)<div class="is-wide"><dt>Meta de producto relacionada</dt><dd>{{ $metaProducto->codigo }} — {{ $metaProducto->nombre }}</dd></div>@endif
                    @if($indicadorResultado)<div class="is-wide"><dt>Indicador de resultado relacionado</dt><dd>{{ $indicadorResultado->codigo }} — {{ $indicadorResultado->nombre }}</dd></div>@endif
                </dl>
            </article>
        @endif
    </div>
</section>
