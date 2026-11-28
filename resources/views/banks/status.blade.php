@extends('layouts.public')
@section('title', 'Consultar solicitud de banco')
@section('content')
<div class="wrap bank-consult-page">
    <div class="bank-page-heading">
        <p>Escriba el número de seguimiento para ver el estado. Si escribe el correo, el estado se envía a esa bandeja y no se muestra en esta pantalla.</p>
    </div>
    <section class="bank-status status-page" id="consultar-estado" aria-labelledby="status-title">
        <h1 id="status-title" class="visually-hidden">Consultar estado</h1>
        @if ($errors->lookup->any())
            <div class="bank-errors" role="alert" id="lookup-errors" tabindex="-1"><button type="button" class="error-dismiss" data-dismiss-error aria-label="Cerrar mensaje de error"><span aria-hidden="true">×</span></button><ul>@foreach ($errors->lookup->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if (session('bank_status_notice'))
            <div class="bank-success" role="status"><p>{{ session('bank_status_notice') }}</p></div>
        @endif
        <form class="lookup-form lookup-single" method="POST" action="{{ route('banks.status') }}">
            @csrf
            <label for="lookup">Número de seguimiento o correo electrónico
                <input id="lookup" name="lookup" type="text" placeholder="Número de seguimiento o correo" value="{{ old('lookup') }}" required maxlength="254" autocomplete="off" aria-describedby="lookup-help @if($errors->lookup->has('lookup')) lookup-errors @endif" @if($errors->lookup->has('lookup')) aria-invalid="true" @endif>
            </label>
            <button class="request-button" type="submit">Consultar <span aria-hidden="true">→</span></button>
        </form>
        <p id="lookup-help" class="bank-lookup-hint">Con el número verá esta solicitud. Con el correo, el estado llega a esa bandeja.</p>
    </section>
    @if ($resultado)
        <div class="bank-results-heading"><h2>Resultados de la consulta</h2><span>1 solicitud encontrada</span></div>
        @php($result = $resultado)
        <section class="bank-result" aria-labelledby="result-reference" role="status">
            <header class="bank-result-header">
                <div>
                    <p class="result-label">Número de seguimiento</p>
                    <h2 id="result-reference" class="reference">{{ $result['reference'] }}</h2>
                    <p>{{ $result['secretariat'] }} · Recibido el {{ $result['received_at'] }}</p>
                </div>
                <span class="result-status result-status-{{ $result['status_value'] }}">{{ $result['status'] }}</span>
            </header>
            <div class="bank-result-body">
                <div>
                    <h3>Formato</h3>
                    <p class="result-filename">{{ $result['filename'] }}</p>
                    <p>{{ $result['file_size'] }} MB</p>
                    @if ($result['annex_filename'])
                        <h3>Anexo</h3>
                        <p>{{ $result['annex_filename'] }}</p>
                    @endif
                    @if ($result['subject'])
                        <h3>Asunto</h3>
                        <p>{{ $result['subject'] }}</p>
                    @endif
                    @if ($result['observacion'])
                        <h3>Observación de Planeación</h3>
                        <p>{{ $result['observacion'] }}</p>
                    @endif
                </div>
                <div>
                    <h3>Seguimiento</h3>
                    <ol class="result-timeline">
                        @forelse ($result['historial'] as $paso)
                            <li>
                                <strong class="result-mark result-mark-{{ $paso['estado_valor'] }}">{{ $paso['estado'] }}</strong>
                                <span>{{ $paso['cuando'] }}</span>
                                @if ($paso['observacion'])<p>{{ $paso['observacion'] }}</p>@endif
                            </li>
                        @empty
                            <li><strong class="result-mark result-mark-{{ $result['status_value'] }}">{{ $result['status'] }}</strong><p>La solicitud quedó registrada.</p></li>
                        @endforelse
                    </ol>
                    <p><strong>Estado actual:</strong> {{ $result['status'] }}</p>
                </div>
            </div>
            @if ($result['can_correct'])
                <form class="bank-correction" method="POST" action="{{ route('banks.correct') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="numero_seguimiento" value="{{ $result['reference'] }}">
                    <h3>Enviar la corrección</h3>
                    <p>El número de seguimiento se conserva. Cargue de nuevo el formato y, si aplica, el anexo.</p>
                    @if ($errors->correction->any() || $errors->any())
                        <div class="bank-errors" role="alert"><ul>@foreach (array_merge($errors->correction->all(), $errors->all()) as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <div class="bank-fields">
                        <div class="field full">
                            <label for="bank_file">Formato corregido <span aria-hidden="true">*</span></label>
                            <div class="bank-upload bank-upload-area">
                                <input id="bank_file" name="bank_file" type="file" accept=".pdf,.doc,.docx" required aria-describedby="bank-file-help">
                                <p id="bank-file-help">PDF o Word (.doc, .docx). Máximo 10 MB.</p>
                            </div>
                        </div>
                        <div class="field full">
                            <label for="annex_file">Anexo</label>
                            <div class="bank-upload bank-upload-area">
                                <input id="annex_file" name="annex_file" type="file" accept=".pdf,.doc,.docx" aria-describedby="annex-file-help">
                                <p id="annex-file-help">Opcional. PDF o Word (.doc, .docx). Máximo 10 MB.</p>
                            </div>
                        </div>
                    </div>
                    <div class="bank-submit-row"><button type="submit" class="request-button">Enviar corrección <span aria-hidden="true">→</span></button></div>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection
