<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\UserRole;
use App\Filament\Resources\AnalisisAreaResource\Pages\MapaAnalisis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SincronizacionMapaAnalisisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_processing_without_a_polygon_warns_that_an_area_is_required(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $componente = Livewire::actingAs($admin)->test(MapaAnalisis::class);

        $html = $componente->html();
        $this->assertMatchesRegularExpression('/<div[^>]*wire:id=/', $html);
        $this->assertDoesNotMatchRegularExpression('/<link[^>]*wire:id=/', $html);
        $componente->assertSeeHtml('data-area-poligono');
        $componente->assertSeeHtml('data-procesar-analisis');
        $componente->assertSeeHtml('disabled');

        $componente->call('procesar')
            ->assertSet('mensaje', 'Dibuje o cargue un área antes de procesar.')
            ->assertNotified('Falta el polígono');
    }
}
