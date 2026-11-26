<?php

namespace Tests\Unit\InteligenciaGeografica;

use App\Enums\FrecuenciaActualizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use Tests\TestCase;

class CadenciaCapaTest extends TestCase
{
    public function test_the_next_update_follows_the_declared_cadence_without_a_connector(): void
    {
        $diaria = new Capa([
            'frecuencia_actualizacion' => FrecuenciaActualizacionCapa::Diaria,
            'fecha_ultima_sincronizacion' => '2026-01-01 08:00:00',
            'ttl_horas' => 24,
        ]);
        $porVersion = new Capa([
            'frecuencia_actualizacion' => FrecuenciaActualizacionCapa::PorVersion,
            'fecha_ultima_sincronizacion' => '2026-01-01 08:00:00',
            'ttl_horas' => 168,
        ]);
        $sinFecha = new Capa([
            'frecuencia_actualizacion' => FrecuenciaActualizacionCapa::Mensual,
            'ttl_horas' => 720,
        ]);

        $this->assertSame('02/01/2026 08:00', $diaria->textoProximaCadencia());
        $this->assertSame('08/01/2026 08:00 (revisión semanal)', $porVersion->textoProximaCadencia());
        $this->assertSame('Sin fecha: el conector está pendiente', $sinFecha->textoProximaCadencia());
    }
}
