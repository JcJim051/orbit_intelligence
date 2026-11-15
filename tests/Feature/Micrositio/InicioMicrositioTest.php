<?php

use App\Enums\UserRole;
use App\Models\MicrositioCifra;
use App\Models\MicrositioHero;
use App\Models\MicrositioMapa;
use App\Models\SiidEje;
use App\Models\SiidIndicador;
use App\Models\User;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Micrositio\CatalogoSiidDePrueba;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    CatalogoSiidDePrueba::asegurar();
});

test('el inicio muestra los contadores dinámicos y el contenido publicado del hero', function () {
    $eje = SiidEje::query()->create(['codigo' => 'SOC', 'nombre' => 'Social', 'orden' => 1]);
    SiidIndicador::query()->create(['codigo' => 'IS-10', 'nombre' => 'Población', 'eje_id' => $eje->id, 'pestana_origen' => 'prueba', 'activo' => true]);
    SiidIndicador::query()->create(['codigo' => 'IS-11', 'nombre' => 'Educación', 'eje_id' => $eje->id, 'pestana_origen' => 'prueba', 'activo' => true]);
    MicrositioHero::query()->create([
        'eyebrow' => 'Sistema Integral de Información Departamental',
        'titulo' => 'Gerencia de Información y Estudios Económicos',
        'subtitulo' => 'Frase de propósito publicada',
    ]);
    MicrositioCifra::factory()->create([
        'valor' => '203',
        'origen' => 'indicadores_sectoriales',
        'etiqueta' => 'Indicadores Sectoriales',
        'es_ejemplo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('hero');
    app(PublicadorMicrositio::class)->publicar('cifras');

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertSee('Sistema Integral de Información Departamental')
        ->assertSee('Gerencia de Información y Estudios Económicos')
        ->assertSee('Indicadores Sectoriales')
        ->assertSee('>2<', false)
        ->assertSee('data-contador="indicadores_sectoriales"', false);
});

test('un contador en cero no se renderiza como botón', function () {
    MicrositioCifra::factory()->create([
        'valor' => '0',
        'origen' => 'indicadores_sectoriales',
        'etiqueta' => 'Indicadores Sectoriales',
        'es_ejemplo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('cifras');

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertSee('Indicadores Sectoriales')
        ->assertDontSee('data-contador', false);
});

test('el bloque del mapa no se renderiza cuando está apagado', function () {
    MicrositioMapa::query()->create([
        'mostrar_bloque' => false,
        'capa_principal' => 'NBI-2018',
        'capas_adicionales' => [],
    ]);
    app(PublicadorMicrositio::class)->publicar('mapa');

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertDontSee('data-mapa-inicio', false);
});

test('la vista previa del inicio muestra el borrador solo a un administrador', function () {
    $hero = MicrositioHero::query()->create([
        'titulo' => 'Título publicado',
        'subtitulo' => 'Propósito publicado',
        'eyebrow' => 'Antetítulo publicado',
    ]);
    app(PublicadorMicrositio::class)->publicar('hero');
    $hero->update(['eyebrow' => 'Antetítulo en borrador']);

    $this->get(route('micrositio.inicio', ['preview' => 1]))
        ->assertOk()
        ->assertSee('Antetítulo publicado')
        ->assertDontSee('Antetítulo en borrador');

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->get(route('micrositio.inicio', ['preview' => 1]))
        ->assertOk()
        ->assertSee('Antetítulo en borrador')
        ->assertSee('VISTA PREVIA DEL BORRADOR');
});
