@extends('layouts.public')

@section('title', 'Solicitud de banco')

@section('content')
<section class="bank-intro bank-request-page wrap" aria-labelledby="request-title">
    <div class="bank-page-heading">
        <h1 id="request-title" class="visually-hidden">Solicitar banco</h1>
        <p>Identifique la dependencia con su código y adjunte el formato del banco de proyectos.</p>
    </div>
    <div class="bank-layout">
        <aside class="bank-guidance" aria-labelledby="bank-guidance-title">
            <h2 id="bank-guidance-title">Antes de comenzar</h2>
            <ol class="bank-steps">
                <li><strong>Escriba el código</strong><span>Planeación se lo entrega a la secretaría. El sistema muestra la dependencia para que la confirme.</span></li>
                <li><strong>Adjunte el formato</strong><span>PDF o Word, con un tamaño máximo de 10 MB. El anexo es opcional.</span></li>
                <li><strong>Conserve su número</strong><span>Lo verá al enviar y también llegará al correo que escriba.</span></li>
            </ol>
            <div class="bank-guidance-consult"><p>¿Ya envió una solicitud?</p><a href="{{ route('banks.status.create') }}">Consultar estado <span aria-hidden="true">→</span></a></div>
        </aside>
        <div>
            @if (session('bank_request_reference'))
                <div class="bank-success" role="status">
                    <h2>Solicitud recibida</h2>
                    <p>Su información y el formato del banco se guardaron correctamente. También enviamos el número al correo que escribió.</p>
                    <p><strong>Número de seguimiento:</strong><br><span class="reference">{{ session('bank_request_reference') }}</span></p>
                    <p><a href="{{ route('banks.status.create') }}">Consultar el estado de esta solicitud</a></p>
                </div>
            @endif
            <section class="bank-form-panel" aria-labelledby="bank-data-title">
                <div class="bank-card-heading"><h2 id="bank-data-title">Datos de la solicitud</h2><p>Los campos con <span aria-hidden="true">*</span> son obligatorios.</p></div>
                <div class="bank-form-content">
                    @if ($errors->any())
                        <div class="bank-errors" role="alert" tabindex="-1"><button type="button" class="error-dismiss" data-dismiss-error aria-label="Cerrar mensaje de error"><span aria-hidden="true">×</span></button>
                            <strong>Revise los siguientes campos:</strong>
                            <ul>@foreach ($errors->messages() as $field => $messages)<li><a href="#{{ $field }}">{{ $messages[0] }}</a></li>@endforeach</ul>
                        </div>
                    @endif

                    @unless ($dependencia)
                        <form method="POST" action="{{ route('banks.identify') }}" novalidate>
                            @csrf
                            <div class="bank-fields">
                                <div class="field full">
                                    <label for="codigo">Código de la dependencia <span aria-hidden="true">*</span></label>
                                    <input id="codigo" name="codigo" type="text" value="{{ old('codigo') }}" maxlength="20" autocomplete="off" required @error('codigo') aria-invalid="true" aria-describedby="codigo-error" @enderror>
                                    @error('codigo')<p class="field-error" id="codigo-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="bank-submit-row"><span class="bank-submit-note">El código identifica la secretaría. No lo publique.</span><button type="submit" class="request-button">Continuar <span aria-hidden="true">→</span></button></div>
                        </form>
                    @else
                        <div class="bank-success" role="status">
                            <p><strong>Dependencia:</strong> {{ $dependencia->nombre }}</p>
                            <p><a href="{{ route('banks.create', ['cambiar' => 1]) }}">Cambiar código</a></p>
                        </div>
                        <form method="POST" action="{{ route('banks.store') }}" enctype="multipart/form-data" novalidate>
                            @csrf
                            <input type="hidden" name="codigo" value="{{ $codigo }}">
                            <div class="bank-fields">
                                <div class="field">
                                    <label for="subject">Asunto <span aria-hidden="true">*</span></label>
                                    <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="200" required @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                                    @error('subject')<p class="field-error" id="subject-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field">
                                    <label for="official_name">Nombre del funcionario <span aria-hidden="true">*</span></label>
                                    <input id="official_name" name="official_name" type="text" value="{{ old('official_name') }}" maxlength="150" autocomplete="name" required @error('official_name') aria-invalid="true" aria-describedby="official_name-error" @enderror>
                                    @error('official_name')<p class="field-error" id="official_name-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field full">
                                    <label for="email">Correo electrónico <span aria-hidden="true">*</span></label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                                    <p id="email-help">Ahí enviaremos el número de seguimiento y cada cambio de estado.</p>
                                    @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field full">
                                    <label for="bank_file">Formato <span aria-hidden="true">*</span></label>
                                    <div class="bank-upload bank-upload-area">
                                        <input id="bank_file" name="bank_file" type="file" accept=".pdf,.doc,.docx" required aria-describedby="bank-file-help @error('bank_file') bank_file-error @enderror" @error('bank_file') aria-invalid="true" @enderror>
                                        <p id="bank-file-help">PDF o Word (.doc, .docx). Máximo 10 MB.</p>
                                    </div>
                                    <p id="bank-preview-message" class="file-preview-message" role="status" hidden></p>
                                    <details class="pdf-preview" id="bank-pdf-preview" open hidden>
                                        <summary>Vista previa del PDF</summary>
                                        <iframe title="Vista previa del documento seleccionado" id="bank-pdf-frame"></iframe>
                                        <a id="bank-pdf-link" target="_blank" rel="noopener">Abrir PDF en otra pestaña</a>
                                    </details>
                                    @error('bank_file')<p class="field-error" id="bank_file-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="field full">
                                    <label for="annex_file">Anexo</label>
                                    <div class="bank-upload bank-upload-area">
                                        <input id="annex_file" name="annex_file" type="file" accept=".pdf,.doc,.docx" aria-describedby="annex-file-help @error('annex_file') annex_file-error @enderror" @error('annex_file') aria-invalid="true" @enderror>
                                        <p id="annex-file-help">Opcional. PDF o Word (.doc, .docx). Máximo 10 MB.</p>
                                    </div>
                                    @error('annex_file')<p class="field-error" id="annex_file-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="bank-submit-row"><span class="bank-submit-note">Revise la información antes de enviar.</span><button type="submit" class="request-button">Solicitar <span aria-hidden="true">→</span></button></div>
                        </form>
                    @endunless
                </div>
            </section>
        </div>
    </div>
</section>
@endsection
