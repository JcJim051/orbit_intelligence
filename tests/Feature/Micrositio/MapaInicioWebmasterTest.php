<?php

use App\Enums\UserRole;
use App\Filament\Pages\MapaDelInicio;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function formularioMapa(array $reemplazo = []): array
{
    return array_merge([
        'mostrar_bloque' => true,
        'capa_principal' => 'NBI-2018',
        'capas_adicionales' => ['POB-2026'],
        'periodo_modo' => 'ultimo',
        'periodo_fijo' => null,
        'paleta' => 'auto',
        'polaridad' => 'auto',
        'texto_tarjeta' => '29 municipios · capital Villavicencio',
        'texto_cta' => 'Explorar en el visor',
        'url_cta' => '/demostracion/geovisor',
        'url_ficha_tecnica' => null,
        'url_indicadores_municipio' => null,
    ], $reemplazo);
}

test('guardar cuatro capas adicionales falla', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(MapaDelInicio::class)
        ->fillForm(formularioMapa([
            'capas_adicionales' => ['POB-2026', 'IPM-2018', 'EDU-CN-2024', 'OTRA'],
        ]))
        ->call('guardar')
        ->assertHasFormErrors(['capas_adicionales']);
});

test('la capa principal no puede repetirse entre las adicionales', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(MapaDelInicio::class)
        ->fillForm(formularioMapa([
            'capa_principal' => 'NBI-2018',
            'capas_adicionales' => ['NBI-2018'],
        ]))
        ->call('guardar')
        ->assertHasFormErrors(['capas_adicionales']);
});

test('el periodo fijo es obligatorio cuando el modo es fijo', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(MapaDelInicio::class)
        ->fillForm(formularioMapa([
            'periodo_modo' => 'fijo',
            'periodo_fijo' => null,
        ]))
        ->call('guardar')
        ->assertHasFormErrors(['periodo_fijo']);
});

test('publicar el mapa cambia el inicio público', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(MapaDelInicio::class)
        ->fillForm(formularioMapa([
            'capa_principal' => 'POB-2026',
            'capas_adicionales' => ['EDU-CN-2024'],
            'texto_tarjeta' => '29 municipios · capital Villavicencio',
        ]))
        ->callAction('publicar')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('micrositio_secciones', ['clave' => 'mapa']);
    auth()->logout();
    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertSee('POB-2026 — Población total proyectada', false);
    $this->get(route('micrositio.mapa.datos'))
        ->assertOk()
        ->assertJsonPath('capas.0.codigo', 'POB-2026');
});

test('quien no administra el micrositio no abre el mapa del inicio', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Member]));
    Filament::setCurrentPanel(Filament::getPanel('management'));

    $this->get('/gestion/mapa-del-inicio')->assertForbidden();
});
