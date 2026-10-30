<?php

use App\Enums\UserRole;
use App\Filament\Resources\MicrositioCifraResource\Pages\ListMicrositioCifras;
use App\Models\MicrositioCifra;
use App\Models\MicrositioMenu;
use App\Models\User;
use App\Services\Micrositio\PublicadorMicrositio;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('una cifra en borrador no aparece en el sitio público', function () {
    MicrositioCifra::factory()->create([
        'valor' => '$3,95 billones',
        'descripcion' => 'Presupuesto definitivo de inversión y funcionamiento (pasiva)',
        'fuente' => 'Ejecución presupuestal acumulada – Secretaría de Hacienda',
        'fecha_corte' => '2026-08-31',
        'es_ejemplo' => false,
    ]);

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertDontSee('$3,95 billones');
});

test('publicar la sección muestra la cifra, la fuente y la fecha de corte', function () {
    $cifra = MicrositioCifra::factory()->create([
        'valor' => '$3,95 billones',
        'descripcion' => 'Presupuesto definitivo de inversión y funcionamiento (pasiva)',
        'fuente' => 'Ejecución presupuestal acumulada – Secretaría de Hacienda',
        'fecha_corte' => '2026-08-31',
        'es_ejemplo' => false,
    ]);

    app(PublicadorMicrositio::class)->publicar('cifras');

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertSee('$3,95 billones')
        ->assertSee('Ejecución presupuestal acumulada – Secretaría de Hacienda')
        ->assertSee('31/08/2026')
        ->assertSee('dato real');

    expect(app(PublicadorMicrositio::class)->estadoDe('cifras', $cifra->fresh()))->toBe('publicado');
});

test('editar el borrador conserva la versión publicada hasta volver a publicar', function () {
    $cifra = MicrositioCifra::factory()->create([
        'valor' => '33,0 %',
        'fuente' => 'Secretaría de Hacienda',
        'es_ejemplo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('cifras');

    $cifra->update(['valor' => '99,0 %']);

    $this->get(route('micrositio.inicio'))->assertOk()->assertSee('33,0 %')->assertDontSee('99,0 %');
    expect(app(PublicadorMicrositio::class)->estadoDe('cifras', $cifra->fresh()))->toBe('borrador');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->get(route('micrositio.inicio', ['preview' => 1]))
        ->assertOk()
        ->assertSee('99,0 %')
        ->assertSee('VISTA PREVIA DEL BORRADOR');

    auth()->logout();

    $this->get(route('micrositio.inicio', ['preview' => 1]))
        ->assertOk()
        ->assertSee('33,0 %')
        ->assertDontSee('VISTA PREVIA DEL BORRADOR');
});

test('eliminar una cifra del borrador no la quita del sitio hasta publicar de nuevo', function () {
    $cifra = MicrositioCifra::factory()->create([
        'valor' => '15,3 %',
        'es_ejemplo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('cifras');
    $cifra->delete();

    $this->get(route('micrositio.inicio'))->assertSee('15,3 %');

    app(PublicadorMicrositio::class)->publicar('cifras');

    $this->get(route('micrositio.inicio'))->assertDontSee('15,3 %');
});

test('la acción publicar del CMS deja la cifra visible', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    MicrositioCifra::factory()->create([
        'valor' => '56,7 %',
        'fuente' => 'Ejecución presupuestal + catálogo de fuentes',
        'es_ejemplo' => false,
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('management'));

    Livewire::test(ListMicrositioCifras::class)->callAction('publicar');

    $this->get(route('micrositio.inicio'))->assertSee('56,7 %');
});

test('quien no es administrador no abre el catálogo del micrositio', function () {
    $miembro = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($miembro)->get('/gestion/micrositio-cifras')->assertForbidden();
});

test('el administrador abre el catálogo del micrositio', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/gestion/micrositio-cifras')->assertOk();
});

test('el menú ordena Inicio y Políticas Públicas sin duplicar Inicio', function () {
    MicrositioMenu::query()->create(['texto' => 'Inicio', 'url' => '/micrositio', 'orden' => 0]);
    app(PublicadorMicrositio::class)->publicar('menu');

    $respuesta = $this->get(route('micrositio.politicas'));
    $respuesta->assertOk()
        ->assertSeeInOrder(['>Inicio</a>', '>Políticas Públicas</a>'], false)
        ->assertSee('¿Qué son las políticas públicas?')
        ->assertDontSee('>SIID</a>', false)
        ->assertSee('aria-current="page"', false);
    expect(substr_count($respuesta->getContent(), '>Inicio</a>'))->toBe(1);
});
