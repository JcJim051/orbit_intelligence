<?php

use App\Enums\UserRole;
use App\Models\MicrositioCifra;
use App\Models\MicrositioHero;
use App\Models\MicrositioPolitica;
use App\Models\User;
use App\Services\Micrositio\ContadoresMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('el inicio muestra el hero claro con contadores y conserva las cifras presupuestales', function () {
    MicrositioCifra::factory()->create([
        'valor' => '$3,95 billones',
        'origen' => 'manual',
        'descripcion' => 'Presupuesto definitivo de inversión y funcionamiento (pasiva)',
        'fuente' => 'Ejecución presupuestal acumulada – Secretaría de Hacienda',
        'fecha_corte' => '2026-08-31',
        'es_ejemplo' => false,
        'orden' => 1,
    ]);
    app(PublicadorMicrositio::class)->publicar('cifras');

    $respuesta = $this->get(route('micrositio.inicio'));

    $respuesta->assertOk()
        ->assertSee('Sistema Integral de Información Departamental')
        ->assertSee('planear, decidir y hacer seguimiento a la gestión pública')
        ->assertSee('Indicadores Sectoriales')
        ->assertSee('Indicadores Territoriales (Propios)')
        ->assertSee('Políticas Públicas')
        ->assertSee('Visor geográfico')
        ->assertSee('data-mapa-inicio', false)
        ->assertSee('$3,95 billones')
        ->assertSee('Indicadores destacados por área temática')
        ->assertDontSee('class="hero"', false);
});

test('publicar el hero reemplaza el antetítulo, la frase y las píldoras', function () {
    MicrositioHero::query()->create([
        'eyebrow' => 'Antetítulo editado',
        'titulo' => 'Título editado del hero',
        'subtitulo' => 'Frase editada del hero para el Meta.',
        'fuentes_linea' => 'Fuente editada del hero',
        'pildoras' => [['texto' => 'Catálogo propio', 'url' => '/micrositio/indicadores']],
        'usar_imagen_fondo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('hero');

    $this->get(route('micrositio.inicio'))
        ->assertOk()
        ->assertSee('Antetítulo editado')
        ->assertSee('Frase editada del hero para el Meta.')
        ->assertSee('Catálogo propio')
        ->assertSee('Fuente editada del hero')
        ->assertDontSee('planear, decidir y hacer seguimiento');
});

test('el contador de políticas ignora los ejemplos y queda en caché', function () {
    MicrositioPolitica::factory()->create(['es_ejemplo' => false, 'nombre' => 'Política vigente']);
    MicrositioPolitica::factory()->create(['es_ejemplo' => true, 'nombre' => 'Política de ejemplo']);
    app(PublicadorMicrositio::class)->publicar('politicas');

    $contadores = app(ContadoresMicrositio::class);

    expect($contadores->valor('politicas_publicas', 'activos'))->toBe(1)
        ->and(Cache::has('micrositio:contador:politicas_publicas:activos'))->toBeTrue();

    ContadoresMicrositio::olvidar();

    expect(Cache::has('micrositio:contador:politicas_publicas:activos'))->toBeFalse();
});

test('el detalle de un origen desconocido responde 404', function () {
    $this->getJson(route('micrositio.contadores.detalle', 'origen-falso'))->assertNotFound();
});

test('el detalle de políticas devuelve el total publicado', function () {
    MicrositioPolitica::factory()->create(['es_ejemplo' => false]);
    app(PublicadorMicrositio::class)->publicar('politicas');

    $this->getJson(route('micrositio.contadores.detalle', 'politicas_publicas'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('origen', 'politicas_publicas');
});

test('la migración del hero puede correr otra vez sin duplicar contadores', function () {
    $migracion = require database_path('migrations/2026_11_16_134339_add_campos_de_hero_a_micrositio.php');
    $migracion->up();

    expect(Schema::hasColumn('micrositio_heroes', 'eyebrow'))->toBeTrue()
        ->and(Schema::hasColumn('micrositio_cifras', 'origen'))->toBeTrue()
        ->and(MicrositioCifra::query()->where('origen', 'indicadores_sectoriales')->count())->toBe(1);
});

test('el webmaster abre el hero y las cifras', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $this->get('/gestion/micrositio-hero')->assertOk();
    $this->get('/gestion/micrositio-cifras')->assertOk();
});
