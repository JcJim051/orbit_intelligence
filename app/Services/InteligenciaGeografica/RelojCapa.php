<?php

namespace App\Services\InteligenciaGeografica;

/**
 * Tope de pared por capa. Las pruebas pueden marcarlo agotado sin esperar el reloj.
 */
class RelojCapa
{
    private float $inicio = 0;

    public bool $agotado = false;

    public function empezar(): void
    {
        $this->inicio = microtime(true);
    }

    public function verificar(): void
    {
        $limite = (int) config('inteligencia.http.capa_timeout');

        if ($this->agotado || ($limite > 0 && $this->inicio > 0 && (microtime(true) - $this->inicio) > $limite)) {
            throw new TiempoCapaAgotado(
                'Se agotó el tiempo máximo de la capa ('.$limite.' s).',
            );
        }
    }
}
