@php
    use App\Enums\BankRequestFileKind;
    use App\Enums\BankRequestStatus;

    $formato = $solicitud->archivoActual(BankRequestFileKind::Formato);
    $anexo = $solicitud->archivoActual(BankRequestFileKind::Anexo);
    $versionVigente = max($formato?->version ?? 1, $anexo?->version ?? 1);
    $anteriores = $solicitud->files->sortBy('id')->groupBy('version')
        ->reject(fn ($archivos, $version): bool => (int) $version === (int) $versionVigente);
    $eventos = $solicitud->events->sortBy('id');
    $estado = $solicitud->status instanceof BankRequestStatus ? $solicitud->status : BankRequestStatus::Recibido;
@endphp

<div class="banco-revision">
    <header class="banco-revision-encabezado">
        <p>Expediente {{ $solicitud->numero_seguimiento }}</p>
        <h2>{{ $solicitud->subject }}</h2>
        <span class="banco-estado banco-estado-{{ $estado->value }}">{{ $estado->label() }}</span>
    </header>

    <dl class="banco-ficha">
        <div><dt>Dependencia</dt><dd>{{ $solicitud->dependencia?->nombre ?? 'Sin dependencia' }}</dd></div>
        <div><dt>Funcionario de la secretaría</dt><dd>{{ $solicitud->official_name }}</dd></div>
        <div><dt>Correo</dt><dd>{{ $solicitud->email }}</dd></div>
        <div><dt>Radicada</dt><dd>{{ $solicitud->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd></div>
    </dl>

    <section class="banco-paso" aria-labelledby="paso-documento">
        <h3 id="paso-documento">1. Documento recibido</h3>
        <p>Revise el formato vigente de la secretaría asignada. Si ya hubo una corrección, esta es la última versión.</p>
        <ul>
            @if ($formato)
                <li>
                    <span>Formato · versión {{ $formato->version }}</span>
                    @include('filament.bancos.archivo-acciones', ['archivo' => $formato])
                </li>
            @endif
            <li>
                <span>Anexo · versión {{ $anexo->version ?? $versionVigente }}</span>
                @if ($anexo)
                    @include('filament.bancos.archivo-acciones', ['archivo' => $anexo])
                @else
                    <em>Sin anexo</em>
                @endif
            </li>
        </ul>
    </section>

    @can('update', $solicitud)
        <section class="banco-paso" aria-labelledby="paso-decision">
            <h3 id="paso-decision">2. Decisión</h3>
            <p>Elija el estado. Si lo devuelve, escriba por qué: la secretaría ve ese motivo, corrige el archivo y lo reenvía con este mismo número.</p>
            <div class="banco-decisiones">
                <button type="button" class="es-proceso" wire:click="mountAction('enProceso')">En proceso</button>
                <button type="button" class="es-aceptar" wire:click="mountAction('aceptar')">Aceptar</button>
                <button type="button" class="es-devolver" wire:click="mountAction('devolver')">Devolver</button>
            </div>
        </section>
    @endcan

    <section class="banco-paso" aria-labelledby="paso-traza">
        <h3 id="paso-traza">3. Trazabilidad</h3>
        <p>Cada cambio de estado y cada reenvío queda en este expediente.</p>

        <h4>Versiones anteriores</h4>
        @if ($anteriores->isEmpty())
            <p class="banco-vacio">Todavía no hay correcciones. Si devuelve el formato, la nueva carga aparecerá aquí.</p>
        @else
            @foreach ($anteriores as $version => $archivos)
                <p class="banco-version">Versión {{ $version }}</p>
                <ul>
                    @foreach ($archivos as $archivo)
                        <li>
                            <span>{{ $archivo->kind->label() }}</span>
                            @include('filament.bancos.archivo-acciones', ['archivo' => $archivo])
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif

        <h4>Historial</h4>
        <ol class="banco-historial">
            @foreach ($eventos as $evento)
                <li>
                    <strong class="banco-estado banco-estado-{{ $evento->to_status->value }}">{{ $evento->to_status->label() }}</strong>
                    <time datetime="{{ $evento->created_at?->toIso8601String() }}">{{ $evento->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
                    @if ($evento->user)
                        <span>{{ $evento->user->name }}</span>
                    @endif
                    @if ($evento->observation)
                        <blockquote>{{ $evento->observation }}</blockquote>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>
