<?php

namespace Tests\Unit\InteligenciaGeografica;

use App\Contracts\ConsultaCapa;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;
use RuntimeException;
use Tests\TestCase;

class ConsultaCapaNoImplementadaTest extends TestCase
{
    public function test_layer_query_is_not_implemented_yet(): void
    {
        $consulta = $this->app->make(ConsultaCapa::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('todavía no está implementada');

        $consulta->consultar(new AnalisisArea, new Capa);
    }
}
