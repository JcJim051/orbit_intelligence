<?php

namespace Tests\Unit\InteligenciaGeografica;

use App\Contracts\ConsultaCapa;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;
use RuntimeException;
use Tests\TestCase;

class ConsultaCapaNoImplementadaTest extends TestCase
{
    public function test_a_layer_without_a_public_source_is_not_invented(): void
    {
        $consulta = $this->app->make(ConsultaCapa::class);
        $capa = new Capa;
        $capa->codigo = 'CAPA-SIN-FUENTE';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no tiene una fuente pública consultable');

        $consulta->consultar(new AnalisisArea, $capa);
    }
}
