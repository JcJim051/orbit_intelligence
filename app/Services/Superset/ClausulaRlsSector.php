<?php

namespace App\Services\Superset;

use App\Models\Dependencia;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ClausulaRlsSector
{
    /**
     * @return list<array{clause: string}>
     */
    public function para(User $user, ?int $dependenciaId = null): array
    {
        if ($user->veTodosLosSectores() && $dependenciaId === null) {
            return [];
        }

        $consulta = $user->veTodosLosSectores()
            ? Dependencia::query()
            : $user->dependencias();

        if ($dependenciaId !== null) {
            $consulta->where('dependencias.id', $dependenciaId);
        }

        $dependencias = $consulta->orderBy('dependencias.codigo')->get();

        if ($dependencias->isEmpty()) {
            throw new AccessDeniedHttpException('El usuario no tiene un sector o dependencia para filtrar el tablero.');
        }

        $partes = [];

        foreach ($dependencias as $dependencia) {
            $partes[] = 'sector = '.$this->literal($dependencia->etiqueta());

            if (preg_match('/^[0-9A-Za-z.\-]+$/', (string) $dependencia->codigo) === 1) {
                $partes[] = 'sector LIKE '.$this->literal($dependencia->codigo.' — %');
            }
        }

        return [['clause' => implode(' OR ', $partes)]];
    }

    private function literal(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
