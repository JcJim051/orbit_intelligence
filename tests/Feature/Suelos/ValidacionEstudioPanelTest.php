<?php

namespace Tests\Feature\Suelos;

use App\Enums\UserRole;
use App\Models\Suelos\EstudioSuelos;
use App\Models\User;
use App\Services\Suelos\FlujoValidacionEstudio;
use Database\Seeders\SuelosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class ValidacionEstudioPanelTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->seed(SuelosCatalogoSeeder::class);
    }

    public function test_the_uploader_cannot_validate_or_reject_the_study(): void
    {
        $autor = User::factory()->create(['role' => UserRole::Reviewer]);
        $revisor = User::factory()->create(['role' => UserRole::Reviewer]);
        $estudio = EstudioSuelos::factory()->create(['cargado_por' => $autor->id]);
        $flujo = app(FlujoValidacionEstudio::class);

        $this->assertFalse($autor->can('validar', $estudio));
        $this->assertTrue($revisor->can('validar', $estudio));

        try {
            $flujo->validar($estudio, $autor, null);
            $this->fail('El autor no debe validar su propio estudio.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('estudio', $excepcion->errors());
        }

        try {
            $flujo->rechazar($estudio, $revisor, '   ');
            $this->fail('El rechazo exige un comentario.');
        } catch (ValidationException $excepcion) {
            $this->assertArrayHasKey('comentario', $excepcion->errors());
        }

        $flujo->rechazar($estudio->refresh(), $revisor, 'Falta el perfil estratigráfico.');
        $this->assertSame('RECHAZADO', $estudio->refresh()->estadoValidacion->codigo);
        $this->assertSame($revisor->id, $estudio->revisado_por);
    }
}
