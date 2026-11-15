<?php

use App\Enums\UserRole;
use App\Filament\Resources\MicrositioCifraResource\Pages\ListMicrositioCifras;
use App\Models\MicrositioCifra;
use App\Models\User;
use App\Services\Micrositio\HojaMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('los campos de origen de una cifra se publican', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(ListMicrositioCifras::class)
        ->callAction('create', data: [
            'origen' => 'politicas_publicas',
            'criterio' => 'activos',
            'etiqueta' => 'Políticas Públicas',
            'valor' => '18',
            'descripcion' => 'Políticas publicadas',
            'fuente' => 'Micrositio',
            'fecha_corte' => '2026-11-13',
            'orden' => 1,
            'es_ejemplo' => false,
        ])
        ->assertHasNoActionErrors();

    expect(MicrositioCifra::query()->first())
        ->origen->toBe('politicas_publicas')
        ->etiqueta->toBe('Políticas Públicas');

    Livewire::test(ListMicrositioCifras::class)->callAction('publicar');
    auth()->logout();
    $this->get(route('micrositio.inicio'))->assertOk()->assertSee('Políticas Públicas');
});

test('la hoja de excel conserva origen criterio y etiqueta', function () {
    MicrositioCifra::factory()->create([
        'valor' => '12',
        'origen' => 'indicadores_territoriales',
        'criterio' => 'publicados_micrositio',
        'etiqueta' => 'Indicadores Territoriales (Propios)',
        'descripcion' => 'Territoriales activos',
        'fuente' => 'SIID',
        'fecha_corte' => '2026-11-13',
        'es_ejemplo' => false,
        'orden' => 2,
    ]);
    $ruta = storage_path('framework/cifras-micrositio.xlsx');
    $hoja = app(HojaMicrositio::class);
    $hoja->exportar('cifras', $ruta);
    MicrositioCifra::query()->delete();
    expect($hoja->importar('cifras', $ruta))->toBe(1);

    $cifra = MicrositioCifra::query()->first();
    expect($cifra->origen)->toBe('indicadores_territoriales')
        ->and($cifra->criterio)->toBe('publicados_micrositio')
        ->and($cifra->etiqueta)->toBe('Indicadores Territoriales (Propios)')
        ->and($cifra->valor)->toBe('12');

    app(PublicadorMicrositio::class)->publicar('cifras');
    $this->get(route('micrositio.inicio'))->assertOk()->assertSee('Indicadores Territoriales (Propios)');
});
