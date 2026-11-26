@extends('layouts.public')
@section('title', 'Consultar solicitud de banco')
@section('content')
<div class="wrap bank-consult-page">
    <div class="bank-page-heading">
        <p>Revise el estado y los documentos registrados en su solicitud.</p>
    </div>
            <section class="bank-status status-page" id="consultar-estado" aria-labelledby="status-title">

                @if ($errors->lookup->any())
                    <div class="bank-errors" role="alert" id="lookup-errors" tabindex="-1"><button type="button" class="error-dismiss" data-dismiss-error aria-label="Cerrar mensaje de error"><span aria-hidden="true">×</span></button><ul>@foreach ($errors->lookup->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form class="lookup-form lookup-single" method="POST" action="{{ route('banks.status') }}">
                    @csrf
                    <label for="lookup">Correo electrónico o número de seguimiento
                        <input id="lookup" name="lookup" type="text" placeholder="Correo o número de seguimiento" value="{{ old('lookup') }}" required maxlength="254" autocomplete="off" aria-describedby="lookup-help @if($errors->lookup->has('lookup')) lookup-errors @endif" @if($errors->lookup->has('lookup')) aria-invalid="true" @endif>
                    </label>
                    <button class="request-button" type="submit">Consultar <span aria-hidden="true">→</span></button>
                </form>
                <p id="lookup-help" class="bank-lookup-hint">Use uno de los dos datos. Con el correo verá sus solicitudes recientes.</p>
            </section>
            @if (session('bank_status_results'))
                <div class="bank-results-heading"><h2>Resultados de la consulta</h2><span>{{ count(session('bank_status_results')) }} {{ count(session('bank_status_results')) === 1 ? 'solicitud encontrada' : 'solicitudes encontradas' }}</span></div>
            @endif
            @foreach (session('bank_status_results', []) as $result)
                <section class="bank-result" aria-labelledby="result-reference-{{ $loop->index }}" role="status">
                    <header class="bank-result-header">
                        <div>
                            <p class="result-label">Número de seguimiento</p>
                            <h2 id="result-reference-{{ $loop->index }}" class="reference">{{ $result['reference'] }}</h2>
                            <p>{{ $result['secretariat'] }} · Recibido el {{ $result['received_at'] }}</p>
                        </div>
                        <span class="result-status">{{ $result['status'] }}</span>
                    </header>
                    <div class="bank-result-body">
                        <div>
                            <h3>Documento adjunto</h3>
                            <p class="result-filename">{{ $result['filename'] }}</p>
                            <p>{{ $result['file_size'] }} MB</p>
                            @if ($result['subject'])
                                <h3>Asunto</h3>
                                <p>{{ $result['subject'] }}</p>
                            @endif
                        </div>
                        <div>
                            <h3>Seguimiento</h3>
                            <ol class="result-timeline">
                                <li><strong>Recibida · {{ $result['received_at'] }}</strong><p>La solicitud y el documento fueron registrados.</p></li>
                            </ol>
                            <p><strong>Estado actual:</strong> {{ $result['status'] }}</p>
                        </div>
                    </div>
                </section>
            @endforeach

</div>
@endsection
