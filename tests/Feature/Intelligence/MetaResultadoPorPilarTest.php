<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaResultadoPorPilarTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_las_metas_resultado_agrupadas_por_pilar_y_filtra(): void
    {
        $this->withoutVite();

        $usuario = User::factory()->create(['role' => UserRole::Manager]);

        $pilarSocial = $this->pilarConPrograma('1', 'Pilar social');
        $programaSocial = $pilarSocial['programa'];
        $subprogramaSocial = PddSubprograma::factory()->create([
            'programa_id' => $programaSocial->id,
            'numeral' => '1.1.1.1.1',
            'nombre' => 'Atención social territorial',
        ]);
        $indicadorSocial = IndicadorResultado::factory()->create([
            'nombre' => 'Población atendida',
            'unidad_medida' => 'Personas',
        ]);
        $metaSocial = MetaResultado::factory()->create([
            'codigo_provisional' => 'MR-001',
            'descripcion' => 'Incrementar la atención social en municipios priorizados',
            'subprograma_id' => $subprogramaSocial->id,
            'indicador_resultado_id' => $indicadorSocial->id,
            'linea_base' => 10,
            'meta_cuatrienio' => 80,
        ]);
        MetaProducto::factory()->count(2)->create([
            'subprograma_id' => $subprogramaSocial->id,
            'meta_resultado_id' => $metaSocial->id,
        ]);

        $pilarEconomico = $this->pilarConPrograma('2', 'Pilar económico');
        MetaResultado::factory()->create([
            'codigo_provisional' => 'MR-002',
            'descripcion' => 'Fortalecer economía regional',
            'programa_id' => $pilarEconomico['programa']->id,
        ]);

        $this->actingAs($usuario)
            ->get(route('intelligence.metas-resultado.por-pilar'))
            ->assertOk()
            ->assertSee('Metas resultado por pilar')
            ->assertSee('Pilar social')
            ->assertSee('MR-001')
            ->assertSee('Población atendida', false)
            ->assertSee('Atención social territorial', false)
            ->assertSee('Pilar económico', false)
            ->assertSee('MR-002')
            ->assertSee(route('intelligence.metas-resultado.por-pilar.download'), false);

        $this->get(route('intelligence.metas-resultado.por-pilar', [
            'pilar_id' => $pilarSocial['pilar']->id,
        ]))
            ->assertOk()
            ->assertSee('Pilar social')
            ->assertSee('MR-001')
            ->assertDontSee('MR-002');

        $this->get(Workspace::getUrl(['workspace' => 'metas-resultado-por-pilar']))
            ->assertOk()
            ->assertSee('Metas resultado por pilar');

        $descarga = $this->get(route('intelligence.metas-resultado.por-pilar.download', [
            'pilar_id' => $pilarSocial['pilar']->id,
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload();

        $contenido = $descarga->streamedContent();
        $this->assertStringContainsString('pilar_codigo;pilar_numeral;pilar_nombre;meta_codigo', $contenido);
        $this->assertStringContainsString('MR-001', $contenido);
        $this->assertStringContainsString('Población atendida', $contenido);
        $this->assertStringNotContainsString('MR-002', $contenido);
    }

    /**
     * @return array{pilar: PddPilar, programa: PddPrograma}
     */
    private function pilarConPrograma(string $numeral, string $nombre): array
    {
        $pilar = PddPilar::factory()->create([
            'numeral' => $numeral,
            'nombre' => $nombre,
        ]);
        $eje = PddEje::factory()->create(['pilar_id' => $pilar->id]);
        $linea = PddLinea::factory()->create(['eje_id' => $eje->id]);
        $programa = PddPrograma::factory()->create(['linea_id' => $linea->id]);

        return ['pilar' => $pilar, 'programa' => $programa];
    }
}
