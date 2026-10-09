<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\TipoAccesoCapa;
use App\Models\InteligenciaGeografica\Capa;
use Database\Seeders\InteligenciaGeograficaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class CapaCatalogoTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
    }

    public function test_seed_registers_eleven_layers_without_unconfirmed_service_urls(): void
    {
        $this->seed(InteligenciaGeograficaSeeder::class);

        $capas = Capa::query()->with('fuente')->orderBy('codigo')->get();

        $this->assertCount(11, $capas);
        $this->assertTrue($capas->every(fn (Capa $capa): bool => $capa->url_servicio === null));
        $this->assertTrue($capas->every(fn (Capa $capa): bool => $capa->licencia === null));
        $this->assertTrue($capas->every(fn (Capa $capa): bool => str_contains((string) $capa->observacion, 'TODO')));
        $this->assertTrue($capas->every(fn (Capa $capa): bool => $capa->activa));

        $suelos = $capas->firstWhere('codigo', 'SIID_ESTUDIOS_SUELOS');
        $this->assertNotNull($suelos);
        $this->assertSame(TipoAccesoCapa::CarguePropio, $suelos->tipo_acceso);
        $this->assertSame('SIID', $suelos->fuente->codigo);
        $this->assertSame('SIID_ESTUDIOS_SUELOS — Estudios de suelos georreferenciados', $suelos->etiqueta());
        $this->assertSame('¿Qué suelo y qué nivel freático se han encontrado cerca del polígono?', $suelos->pregunta);

        $anm = $capas->firstWhere('codigo', 'ANM_TITULOS_SOLICITUDES');
        $this->assertNotNull($anm);
        $this->assertSame(['4', '2'], array_column($anm->endpoints, 'layer_id'));
        $this->assertNull($anm->endpoints[0]['url_servicio']);
        $this->assertNull($anm->endpoints[1]['url_servicio']);

        $drenaje = $capas->firstWhere('codigo', 'META_DRENAJE');
        $this->assertNotNull($drenaje);
        $this->assertCount(2, $drenaje->endpoints);
        $this->assertSame('GOBERNACION_META', $drenaje->fuente->codigo);
    }
}
