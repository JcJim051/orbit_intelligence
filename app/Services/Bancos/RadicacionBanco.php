<?php

namespace App\Services\Bancos;

use App\Enums\BankRequestFileKind;
use App\Enums\BankRequestStatus;
use App\Mail\Bancos\EstadoActualizado;
use App\Mail\Bancos\SolicitudRecibida;
use App\Models\BankRequest;
use App\Models\Dependencia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RadicacionBanco
{
    public function identificar(string $codigo): ?Dependencia
    {
        $codigo = $this->normalizarCodigo($codigo);

        if ($codigo === '') {
            return null;
        }

        return Dependencia::query()
            ->where('activo', true)
            ->where('codigo_radicacion', $codigo)
            ->first();
    }

    public function normalizarCodigo(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }

    public function guardarCodigo(Dependencia $dependencia, ?string $codigo): void
    {
        $codigo = $this->normalizarCodigo($codigo ?? '');
        $codigo = $codigo === '' ? null : $codigo;

        if ($codigo !== null && preg_match('/^[A-Z0-9-]{4,20}$/', $codigo) !== 1) {
            throw ValidationException::withMessages([
                'codigo_radicacion' => 'El código debe tener entre 4 y 20 caracteres, y solo letras, números o guiones.',
            ]);
        }

        if ($codigo !== null && Dependencia::query()->where('codigo_radicacion', $codigo)->whereKeyNot($dependencia->id)->exists()) {
            throw ValidationException::withMessages([
                'codigo_radicacion' => 'Ese código ya está asignado a otra dependencia.',
            ]);
        }

        $dependencia->update(['codigo_radicacion' => $codigo]);
    }

    public function radicar(Dependencia $dependencia, string $subject, string $officialName, string $email, UploadedFile $formato, ?UploadedFile $anexo): BankRequest
    {
        $solicitud = $this->guardarEnvio(null, $dependencia, $subject, $officialName, $email, $formato, $anexo);
        $this->enviar(fn () => Mail::to($solicitud->email)->send(new SolicitudRecibida($solicitud)));

        return $solicitud;
    }

    public function corregir(BankRequest $solicitud, UploadedFile $formato, ?UploadedFile $anexo): BankRequest
    {
        if ($solicitud->status !== BankRequestStatus::Devuelto) {
            throw ValidationException::withMessages([
                'numero_seguimiento' => 'Ese número no admite una corrección en este momento.',
            ]);
        }

        $dependencia = $solicitud->dependencia;
        if (! $dependencia instanceof Dependencia) {
            throw ValidationException::withMessages([
                'numero_seguimiento' => 'Ese número no admite una corrección en este momento.',
            ]);
        }

        $solicitud = $this->guardarEnvio($solicitud, $dependencia, $solicitud->subject, $solicitud->official_name, $solicitud->email, $formato, $anexo);
        $this->enviar(fn () => Mail::to($solicitud->email)->send(new EstadoActualizado($solicitud, 'Recibimos la corrección y el expediente volvió a revisión.')));

        return $solicitud;
    }

    public function cambiarEstado(BankRequest $solicitud, BankRequestStatus $estado, User $revisor, ?string $observacion): BankRequest
    {
        $observacion = trim((string) $observacion);

        if ($estado === BankRequestStatus::Devuelto && mb_strlen($observacion) < 10) {
            throw ValidationException::withMessages([
                'observacion' => 'Escriba la observación que verá la secretaría, con al menos 10 caracteres.',
            ]);
        }

        if ($solicitud->status === $estado && $estado !== BankRequestStatus::Devuelto) {
            return $solicitud;
        }

        $anterior = $solicitud->status;

        DB::transaction(function () use ($solicitud, $estado, $revisor, $observacion, $anterior): void {
            $bloqueada = BankRequest::query()->lockForUpdate()->findOrFail($solicitud->id);
            $bloqueada->update(['status' => $estado]);
            $bloqueada->events()->create([
                'user_id' => $revisor->id,
                'from_status' => $anterior,
                'to_status' => $estado,
                'observation' => $observacion !== '' ? $observacion : null,
            ]);
        });

        $solicitud->refresh();
        $this->enviar(fn () => Mail::to($solicitud->email)->send(new EstadoActualizado(
            $solicitud,
            $observacion !== '' ? $observacion : null,
        )));

        return $solicitud;
    }

    private function guardarEnvio(?BankRequest $existente, Dependencia $dependencia, string $subject, string $officialName, string $email, UploadedFile $formato, ?UploadedFile $anexo): BankRequest
    {
        $rutas = [];

        try {
            return DB::transaction(function () use ($existente, $dependencia, $subject, $officialName, $email, $formato, $anexo, &$rutas): BankRequest {
                $version = $existente instanceof BankRequest
                    ? ((int) $existente->files()->max('version')) + 1
                    : 1;
                $rutaFormato = $formato->store('bank-requests', 'local');
                abort_unless(is_string($rutaFormato), 503, 'No fue posible guardar el archivo. Intente nuevamente.');
                $rutas[] = $rutaFormato;

                $rutaAnexo = null;
                if ($anexo instanceof UploadedFile) {
                    $rutaAnexo = $anexo->store('bank-requests', 'local');
                    abort_unless(is_string($rutaAnexo), 503, 'No fue posible guardar el anexo. Intente nuevamente.');
                    $rutas[] = $rutaAnexo;
                }

                if ($existente instanceof BankRequest) {
                    $solicitud = BankRequest::query()->lockForUpdate()->findOrFail($existente->id);
                    if ($solicitud->status !== BankRequestStatus::Devuelto) {
                        throw ValidationException::withMessages([
                            'numero_seguimiento' => 'Ese número no admite una corrección en este momento.',
                        ]);
                    }
                    $solicitud->update([
                        'status' => BankRequestStatus::Recibido,
                        'file_path' => $rutaFormato,
                        'original_filename' => $formato->getClientOriginalName(),
                        'file_mime_type' => $formato->getMimeType(),
                        'file_size' => $formato->getSize(),
                    ]);
                    $desde = BankRequestStatus::Devuelto;
                    $observacion = 'La secretaría cargó una corrección.';
                    $usuario = null;
                } else {
                    $solicitud = BankRequest::query()->create([
                        'reference' => (string) Str::uuid(),
                        'numero_seguimiento' => $this->numeroSeguimiento(),
                        'dependencia_id' => $dependencia->id,
                        'secretariat' => $dependencia->nombre,
                        'subject' => $subject,
                        'official_name' => $officialName,
                        'email' => strtolower($email),
                        'status' => BankRequestStatus::Recibido,
                        'file_path' => $rutaFormato,
                        'original_filename' => $formato->getClientOriginalName(),
                        'file_mime_type' => $formato->getMimeType(),
                        'file_size' => $formato->getSize(),
                    ]);
                    $desde = null;
                    $observacion = null;
                    $usuario = null;
                }

                $solicitud->files()->create([
                    'version' => $version,
                    'kind' => BankRequestFileKind::Formato,
                    'file_path' => $rutaFormato,
                    'original_filename' => $formato->getClientOriginalName(),
                    'file_mime_type' => $formato->getMimeType(),
                    'file_size' => $formato->getSize(),
                ]);

                if ($anexo instanceof UploadedFile && is_string($rutaAnexo)) {
                    $solicitud->files()->create([
                        'version' => $version,
                        'kind' => BankRequestFileKind::Anexo,
                        'file_path' => $rutaAnexo,
                        'original_filename' => $anexo->getClientOriginalName(),
                        'file_mime_type' => $anexo->getMimeType(),
                        'file_size' => $anexo->getSize(),
                    ]);
                }

                $solicitud->events()->create([
                    'user_id' => $usuario,
                    'from_status' => $desde,
                    'to_status' => BankRequestStatus::Recibido,
                    'observation' => $observacion,
                ]);

                return $solicitud->fresh(['dependencia', 'files', 'events']);
            });
        } catch (Throwable $exception) {
            foreach ($rutas as $ruta) {
                Storage::disk('local')->delete($ruta);
            }

            throw $exception;
        }
    }

    private function numeroSeguimiento(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        for ($intento = 0; $intento < 8; $intento++) {
            $sufijo = '';
            for ($indice = 0; $indice < 6; $indice++) {
                $sufijo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $numero = 'BP-'.now()->year.'-'.$sufijo;
            if (! BankRequest::query()->where('numero_seguimiento', $numero)->exists()) {
                return $numero;
            }
        }

        throw ValidationException::withMessages([
            'bank_file' => 'No fue posible asignar el número de seguimiento. Intente nuevamente.',
        ]);
    }

    private function enviar(callable $envio): void
    {
        try {
            $envio();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
