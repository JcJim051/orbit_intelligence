@extends('micrositio.layout')
@section('titulo', 'Políticas Públicas')
@section('contenido')
<section class="ilayout">
    <aside class="ilist" aria-label="Políticas públicas">
        <h2 class="sidebar-heading">Políticas Públicas</h2>
        <a href="{{ route('micrositio.politicas', $preview ? ['preview' => 1] : []) }}" @if (!$seleccionada) class="on" aria-current="page" @endif>¿Qué son las políticas públicas?</a>
        @foreach ($politicas as $politica)
            <a href="{{ route('micrositio.politicas', ['politica' => $politica['slug']] + ($preview ? ['preview' => 1] : [])) }}" @if ($seleccionada && $seleccionada['slug'] === $politica['slug']) class="on" aria-current="page" @endif>
                <strong>{{ $politica['nombre'] }}</strong>
            </a>
        @endforeach
    </aside>
    <article>
        @if ($seleccionada)
            <h1>{{ $seleccionada['nombre'] }}</h1>
            <p>{{ $seleccionada['descripcion'] }}</p>
            <dl>
                @foreach (['ordenanza' => 'Ordenanza', 'vigencia' => 'Vigencia', 'responsable' => 'Responsable'] as $campo => $etiqueta)
                    @if ($seleccionada[$campo])
                        <dt><strong>{{ $etiqueta }}</strong></dt>
                        <dd>{{ $seleccionada[$campo] }}</dd>
                    @endif
                @endforeach
            </dl>
            @if ($seleccionada['objetivo'])
                <h2>Objetivo</h2>
                <p>{{ $seleccionada['objetivo'] }}</p>
            @endif
            @if (!empty($seleccionada['documentos']))
                <h2>Documentos</h2>
                <div class="policy-documents">
                    @foreach ($seleccionada['documentos'] as $indice => $archivo)
                        @php
                            $nombre = $seleccionada['nombres_documentos'][$archivo] ?? 'Documento '.($indice + 1);
                            $url = route('micrositio.politicas.documento', ['slug' => $seleccionada['slug'], 'indice' => $indice] + ($preview ? ['preview' => 1] : []));
                            $pdf = strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'pdf';
                        @endphp
                        <div class="policy-document">
                            <span class="document-format">{{ $pdf ? 'PDF' : strtoupper(pathinfo($archivo, PATHINFO_EXTENSION)) }}</span>
                            <strong>{{ $nombre }}</strong>
                            <div class="document-actions">
                                @if ($pdf)
                                    <button type="button" data-pdf-preview="{{ $url.(str_contains($url, '?') ? '&' : '?').'inline=1' }}" data-document-name="{{ $nombre }}" data-download-url="{{ $url }}">Vista previa</button>
                                @endif
                                <a href="{{ $url }}" download>Descargar</a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <dialog class="pdf-dialog" aria-labelledby="pdf-dialog-title">
                    <header>
                        <h2 id="pdf-dialog-title">Vista previa</h2>
                        <a data-pdf-download download>Descargar</a>
                        <button type="button" data-pdf-close aria-label="Cerrar vista previa">Cerrar ×</button>
                    </header>
                    <div class="pdf-toolbar" role="group" aria-label="Controles del documento">
                        <button type="button" data-pdf-action="prev" disabled>Anterior</button>
                        <button type="button" data-pdf-action="next" disabled>Siguiente</button>
                        <button type="button" data-pdf-action="minus" aria-label="Reducir zoom" disabled>−</button>
                        <button type="button" data-pdf-action="plus" aria-label="Aumentar zoom" disabled>+</button>
                    </div>
                    <p data-pdf-status role="status" aria-live="polite"></p>
                    <div class="pdf-body">
                        <div data-pdf-page class="pdf-page"></div>
                        <details class="pdf-text"><summary>Texto de esta página</summary><div data-pdf-text></div></details>
                    </div>
                </dialog>
            @endif
        @else
            <h1>Políticas Públicas</h1>
            <h2>¿Qué son las políticas públicas?</h2>
            @if ($politicas)
                <p>Selecciona una política en el menú para consultar su información.</p>
            @else
                <p>Aún no hay políticas publicadas.</p>
            @endif
        @endif
    </article>
</section>
@endsection