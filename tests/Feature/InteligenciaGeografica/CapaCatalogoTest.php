<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\FrecuenciaActualizacionCapa;
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

    public function test_seed_assigns_update_frequency_ttl_and_citation(): void
    {
        $this->seed(InteligenciaGeograficaSeeder::class);

        $capas = Capa::query()->with('fuente')->get()->keyBy('codigo');

        $this->assertSame(FrecuenciaActualizacionCapa::Diaria, $capas['ANM_TITULOS_SOLICITUDES']->frecuencia_actualizacion);
        $this->assertSame(24, $capas['ANM_TITULOS_SOLICITUDES']->ttl_horas);

        $this->assertSame(FrecuenciaActualizacionCapa::Semanal, $capas['RUNAP_AREAS_PROTEGIDAS']->frecuencia_actualizacion);
        $this->assertSame(FrecuenciaActualizacionCapa::Semanal, $capas['ANT_RESGUARDOS_CONSEJOS']->frecuencia_actualizacion);
        $this->assertSame(FrecuenciaActualizacionCapa::Semanal, $capas['META_DRENAJE']->frecuencia_actualizacion);
        $this->assertSame(168, $capas['META_DRENAJE']->ttl_horas);

        $this->assertSame(FrecuenciaActualizacionCapa::Mensual, $capas['IGAC_CATASTRO_R1']->frecuencia_actualizacion);
        $this->assertSame(720, $capas['IGAC_CATASTRO_R1']->ttl_horas);
        $this->assertStringContainsString('avalúo se consulta en vivo', $capas['IGAC_CATASTRO_R1']->observacion);

        foreach (['IDEAM_COBERTURA_TIERRA_2024', 'DANE_GRILLA_POBLACION_1KM', 'CORMACARENA_POT_CLASIFICACION', 'CORMACARENA_ECOSISTEMAS', 'SGC_AMENAZA_MOVIMIENTOS_MASA'] as $codigo) {
            $this->assertSame(FrecuenciaActualizacionCapa::PorVersion, $capas[$codigo]->frecuencia_actualizacion);
            $this->assertSame(168, $capas[$codigo]->ttl_horas);
        }

        $this->assertSame(FrecuenciaActualizacionCapa::TiempoReal, $capas['SIID_ESTUDIOS_SUELOS']->frecuencia_actualizacion);
        $this->assertSame(0, $capas['SIID_ESTUDIOS_SUELOS']->ttl_horas);

        $this->assertTrue($capas->every(fn (Capa $capa): bool => $capa->estado_frescura === EstadoFrescuraCapa::PosiblementeDesactualizada));
        $this->assertTrue($capas->every(fn (Capa $capa): bool => $capa->fecha_corte_fuente === null));
        $this->assertTrue($capas->every(function (Capa $capa): bool {
            return str_contains($capa->cita_fuente, $capa->fuente->nombre)
                && str_contains($capa->cita_fuente, $capa->nombre)
                && str_contains($capa->cita_fuente, 'Licencia pendiente de confirmación');
        }));
    }
}
