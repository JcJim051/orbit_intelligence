<?php

use App\Enums\GeoViewerStatus;
use App\Enums\UserRole;
use App\Models\GeoViewer;
use App\Models\PublicToolPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('un geovisor sin aprobación técnica no puede hacerse visible', function () {
    $viewer = GeoViewer::factory()->create();

    expect(fn () => $viewer->publication()->first()->update(['visible' => true]))
        ->toThrow(ValidationException::class);
});

test('un geovisor aprobado pero oculto no aparece ni responde públicamente', function () {
    $viewer = GeoViewer::factory()->create(['status' => GeoViewerStatus::Published]);
    $viewer->publication()->update(['visible' => false]);

    $this->get(route('micrositio.geovisores.index'))->assertOk()->assertDontSee($viewer->name);
    $this->get(route('micrositio.geovisores.show', $viewer))->assertNotFound();
    $this->get(route('geo-viewers.embed', $viewer))->assertNotFound();
});

test('el webmaster publica un geovisor aprobado sin modificar su configuración cartográfica', function () {
    $webmaster = User::factory()->create(['role' => UserRole::Webmaster]);
    $viewer = GeoViewer::factory()->create(['status' => GeoViewerStatus::Published]);
    $publication = $viewer->publication()->first();

    expect($webmaster->can('update', $publication))->toBeTrue();
    $this->actingAs($webmaster);
    $publication->update(['visible' => true, 'titulo_publico' => 'Mapa público']);

    $auditTable = DB::getDriverName() === 'pgsql'
        ? 'siid_publicacion.publicaciones_geovisores_auditoria'
        : 'publicaciones_geovisores_auditoria';

    $this->assertDatabaseHas($auditTable, [
        'publicacion_geovisor_id' => $publication->id,
        'usuario_id' => $webmaster->id,
        'evento' => 'actualizado',
    ]);

    $this->get(route('micrositio.geovisores.index'))->assertOk()->assertSee('Mapa público');
    $this->get(route('micrositio.geovisores.show', $viewer))->assertOk();
});

test('el gestor SIG no administra la decisión editorial', function () {
    $sig = User::factory()->create(['role' => UserRole::SiidManager]);
    $publication = GeoViewer::factory()->create()->publication()->firstOrFail();

    expect($sig->can('update', $publication))->toBeFalse();
});

test('la herramienta geoespacial apagada responde 404 y encendida muestra la vista pública', function () {
    $tool = PublicToolPublication::geospatialAnalysis();

    $this->get(route('micrositio.analisis-geoespacial.index'))->assertNotFound();

    $tool->update(['visible' => true]);
    $this->get(route('micrositio.analisis-geoespacial.index'))
        ->assertOk()
        ->assertSee('Análisis geoespacial')
        ->assertSee('se eliminan después de 24 horas');
});
