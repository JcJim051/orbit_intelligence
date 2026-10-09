<?php

namespace Tests\Unit\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\Capa;
use DateTimeImmutable;
use Tests\TestCase;

class FrescuraCapaTest extends TestCase
{
    public function test_download_happens_only_when_the_source_edit_date_is_newer(): void
    {
        $capa = new Capa([
            'fecha_corte_fuente' => '2026-03-01 00:00:00',
        ]);

        $this->assertFalse($capa->requiereDescarga(new DateTimeImmutable('2026-03-01 00:00:00')));
        $this->assertFalse($capa->requiereDescarga(new DateTimeImmutable('2026-02-01 00:00:00')));
        $this->assertTrue($capa->requiereDescarga(new DateTimeImmutable('2026-03-01 00:00:01')));
    }

    public function test_download_happens_when_either_edit_date_is_unknown(): void
    {
        $sinCorte = new Capa;
        $conCorte = new Capa([
            'fecha_corte_fuente' => '2026-03-01 00:00:00',
        ]);

        $this->assertTrue($sinCorte->requiereDescarga(new DateTimeImmutable('2026-03-01 00:00:00')));
        $this->assertTrue($conCorte->requiereDescarga(null));
    }

    public function test_local_copy_is_stale_once_it_passes_the_ttl(): void
    {
        $capa = new Capa([
            'ttl_horas' => 24,
            'fecha_ultima_sincronizacion' => '2026-03-01 12:00:00',
        ]);

        $this->assertFalse($capa->copiaExcedeTtl(new DateTimeImmutable('2026-03-02 12:00:00')));
        $this->assertTrue($capa->copiaExcedeTtl(new DateTimeImmutable('2026-03-02 12:00:01')));

        $enVivo = new Capa([
            'ttl_horas' => 0,
            'fecha_ultima_sincronizacion' => '2026-03-01 12:00:00',
        ]);

        $this->assertFalse($enVivo->copiaExcedeTtl(new DateTimeImmutable('2026-03-01 12:00:00')));
        $this->assertTrue($enVivo->copiaExcedeTtl(new DateTimeImmutable('2026-03-01 12:00:01')));
        $this->assertTrue((new Capa(['ttl_horas' => 24]))->copiaExcedeTtl(new DateTimeImmutable('2026-03-01 12:00:00')));
    }
}
