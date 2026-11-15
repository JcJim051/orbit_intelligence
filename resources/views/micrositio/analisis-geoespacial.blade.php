@extends('micrositio.layout')

@section('titulo', $herramienta->titulo.' — SIID')

@section('contenido')
@vite(['resources/js/inteligencia-geografica.js'])
<section class="wrap public-geo-page">
    <header class="public-geo-heading">
        <p class="eyebrow">Herramienta pública</p>
        <h1>{{ $herramienta->titulo }}</h1>
        <p>{{ $herramienta->descripcion }}</p>
        <p><strong>{{ $capasCount }}</strong> capas oficiales habilitadas · los datos temporales se eliminan después de 24 horas.</p>
    </header>
    <div class="public-analysis-layout mapa-primero">
        <div class="public-analysis-map" data-mapa-analisis><script type="application/json" class="mapa-estado">null</script></div>
        <form class="public-analysis-panel" data-public-analysis-form method="post" enctype="multipart/form-data" action="{{ route('micrositio.analisis-geoespacial.store') }}">
            @csrf
            <h2>Defina el área</h2>
            <p>Dibuje un polígono sobre el mapa o cargue KML, KMZ, GeoJSON o shapefile ZIP.</p>
            <p>Área: <strong data-area-poligono>dibuje o cargue un área</strong></p>
            <input type="hidden" name="geojson">
            <label>Archivo (máximo 20 MB)<input data-archivo-analisis name="archivo" type="file" accept=".kmz,.kml,.geojson,.json,.zip"></label>
            <p data-mensaje-analisis class="public-analysis-message" hidden></p>
            <button data-procesar-analisis class="btn" type="submit" disabled>Analizar área</button>
            <div data-analysis-result class="public-analysis-result" hidden aria-live="polite"></div>
        </form>
    </div>
</section>
<script>
const form=document.querySelector('[data-public-analysis-form]');
form?.addEventListener('submit',async(e)=>{e.preventDefault();const out=form.querySelector('[data-analysis-result]');out.hidden=false;out.textContent='Enviando el análisis…';const body=new FormData(form);if(body.get('geojson'))body.delete('archivo');try{const response=await fetch(form.action,{method:'POST',body,headers:{Accept:'application/json'}});const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message);out.textContent='Procesando las capas oficiales…';const poll=async()=>{const status=await fetch(data.status_url,{headers:{Accept:'application/json'}}).then(r=>r.json());if(status.estado==='listo'){renderResult(out,status)}else if(status.estado==='fallido'){out.textContent=status.error}else setTimeout(poll,1800)};poll()}catch(error){out.textContent=error.message}});
function renderResult(out,status){out.replaceChildren();const title=document.createElement('strong');title.textContent='Análisis terminado.';out.append(title,document.createElement('br'),document.createTextNode(`Área: ${status.area_hectareas??'—'} ha · ${status.capas_analizadas} capas.`));const list=document.createElement('div');list.className='public-analysis-layers';for(const result of status.resultados||[]){const row=document.createElement('article');const heading=document.createElement('strong');heading.textContent=result.capa;const metrics=[result.conteo!==null?`${result.conteo} registros`:null,result.area_hectareas!==null?`${result.area_hectareas} ha`:null,result.longitud_km!==null?`${result.longitud_km} km`:null].filter(Boolean).join(' · ');const detail=document.createElement('p');detail.textContent=metrics||'Sin coincidencias cuantificables';const source=document.createElement('small');source.textContent=[result.fuente,result.fecha_corte?`Corte: ${result.fecha_corte}`:null,result.licencia].filter(Boolean).join(' · ');row.append(heading,detail,source);if(result.advertencia){const warning=document.createElement('p');warning.className='public-analysis-warning';warning.textContent=result.advertencia;row.append(warning)}list.append(row)}out.append(list);const link=document.createElement('a');link.className='btn';link.href=status.pdf_url;link.textContent='Descargar informe PDF';out.append(link)}
</script>
@endsection
