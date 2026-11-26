<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Micrositio\PublicadorMicrositio;
use App\Services\Superset\ClausulaRlsSector;
use App\Services\Superset\SupersetClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SupersetGuestTokenController extends Controller
{
    public function __construct(
        private readonly PublicadorMicrositio $publicador,
        private readonly SupersetClient $superset,
        private readonly ClausulaRlsSector $rls,
    ) {}

    public function publico(string $codigo): JsonResponse
    {
        $indicador = $this->indicadorPublicado($codigo)['atributos'] ?? null;

        if ($indicador === null || ! $indicador['es_publico'] || ! filled($indicador['dashboard_uuid'])) {
            abort(403);
        }

        return $this->responder((string) $indicador['dashboard_uuid'], [], true);
    }

    public function autenticado(Request $request, string $codigo): JsonResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User, 401);

        $publicado = $this->indicadorPublicado($codigo);
        $borrador = collect($this->publicador->registrosBorrador('indicadores'))
            ->first(fn (array $registro): bool => ($registro['atributos']['codigo'] ?? null) === $codigo);

        $origen = $usuario->isAdmin() ? ($borrador ?? $publicado) : $publicado;
        $atributos = is_array($origen) && is_array($origen['atributos'] ?? null) ? $origen['atributos'] : null;

        if ($atributos === null || ! filled($atributos['dashboard_uuid'] ?? null)) {
            abort(403);
        }

        try {
            $clausulas = $this->rls->para($usuario, $request->integer('dependencia_id') ?: null);
        } catch (AccessDeniedHttpException) {
            abort(403);
        }

        return $this->responder((string) $atributos['dashboard_uuid'], $clausulas, false);
    }

    /**
     * @return array{id: int, atributos: array<string, mixed>}|null
     */
    private function indicadorPublicado(string $codigo): ?array
    {
        foreach ($this->publicador->registrosPublicados('indicadores') as $registro) {
            if (($registro['atributos']['codigo'] ?? null) === $codigo) {
                return $registro;
            }
        }

        return null;
    }

    /**
     * @param  list<array{clause: string}>  $rls
     */
    private function responder(string $uuid, array $rls, bool $publico): JsonResponse
    {
        try {
            $token = $this->superset->tokenInvitado($uuid, $rls, $publico);
        } catch (RuntimeException $exception) {
            $codigo = str_contains($exception->getMessage(), 'no está configurado') ? 503 : 502;

            return response()->json(['message' => $exception->getMessage()], $codigo);
        }

        return response()->json([
            'token' => $token,
            'uuid' => $uuid,
            'superset_url' => config('superset.url'),
            'rls' => $publico ? [] : $rls,
        ]);
    }
}
