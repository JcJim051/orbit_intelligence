<?php

use App\Enums\UserRole;
use App\Filament\Resources\MicrositioHeroResource\Pages\ListMicrositioHeroes;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('los campos nuevos del hero se guardan y se publican', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(ListMicrositioHeroes::class)
        ->callAction('create', data: [
            'eyebrow' => 'Sistema Integral de Información Departamental',
            'titulo' => 'Gerencia de Información y Estudios Económicos',
            'subtitulo' => 'Frase de propósito del hero',
            'fuentes_linea' => 'Fuentes: SIID · DANE. Corte: 13/11/2026.',
            'pildoras' => [
                ['texto' => 'Indicadores', 'url' => '/micrositio/indicadores'],
            ],
            'usar_imagen_fondo' => false,
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('micrositio_heroes', [
        'eyebrow' => 'Sistema Integral de Información Departamental',
        'fuentes_linea' => 'Fuentes: SIID · DANE. Corte: 13/11/2026.',
        'usar_imagen_fondo' => 0,
    ]);

    Livewire::test(ListMicrositioHeroes::class)->callAction('publicar');
    auth()->logout();

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertSee('Sistema Integral de Información Departamental')
        ->assertSee('Fuentes: SIID · DANE. Corte: 13/11/2026.')
        ->assertSee('Indicadores');
});
