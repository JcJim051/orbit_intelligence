<?php

use App\Enums\UserRole;
use App\Filament\Resources\MicrositioCifraResource\Pages\ListMicrositioCifras;
use App\Filament\Resources\MicrositioPublicacionResource\Pages\ListMicrositioPublicaciones;
use App\Models\MicrositioCifra;
use App\Models\MicrositioMenu;
use App\Models\MicrositioPublicacion;
use App\Models\User;
use App\Services\Micrositio\PublicadorMicrositio;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
    $dom = new DOMDocument;
    @$dom->loadHTML($respuesta->getContent());
    $menu = $dom->getElementById('menu-principal');
    expect(substr_count($dom->saveHTML($menu), '>Inicio</a>'))->toBe(1);
});

test('el micrositio comparte la base visual del kit GOVCO sin afectar el contenido', function () {
    foreach (['micrositio.inicio', 'micrositio.politicas', 'micrositio.indicadores'] as $ruta) {
        $this->get(route($ruta))->assertOk()
            ->assertSee('https://www.gov.co/home/', false)
            ->assertSee('family=Nunito+Sans', false)
            ->assertDontSee('family=Poppins', false)
            ->assertSee('data-micrositio-nav', false);
    }
});

test('la cabecera expone navegación accesible y el indicador activo', function () {
    MicrositioMenu::query()->create(['texto' => 'Indicadores', 'url' => '/micrositio/indicadores', 'orden' => 0]);
    app(PublicadorMicrositio::class)->publicar('menu');
    $this->get(route('micrositio.indicadores'))->assertOk()
        ->assertSee('Saltar al contenido principal')
        ->assertSee('aria-controls="menu-principal"', false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('aria-current="page"', false);
    $html = $this->get(route('micrositio.indicadores'))->getContent();
    expect($html)->toMatch('/aria-current="page"\s*>Indicadores<\/a>/');
});

test('el repositorio usa publicaciones del panel y mantiene los borradores privados', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('management'));
    Livewire::test(ListMicrositioPublicaciones::class)
        ->callAction('create', data: [
            'tipo' => 'Anuario', 'titulo' => 'Anuario del repositorio', 'fecha' => '2026-11-12',
            'resumen' => 'Resumen administrado', 'orden' => 0, 'es_ejemplo' => false,
        ])->assertHasNoActionErrors();

    $url = route('micrositio.publicaciones');
    $this->get($url)->assertDontSee('Anuario del repositorio');
    $this->get($url.'?preview=1')->assertSee('Anuario del repositorio')->assertSee('2026-11-12');
    Livewire::test(ListMicrositioPublicaciones::class)
        ->callAction('publicar')->assertHasNoActionErrors();
    auth()->logout();
    $this->get($url)->assertSee('Anuario del repositorio')->assertSee('Resumen administrado');
    $publicacion = MicrositioPublicacion::query()->firstOrFail();
    $publicacion->update(['titulo' => 'Cambio todavía privado']);
    $this->get($url.'?preview=1')->assertSee('Anuario del repositorio')->assertDontSee('Cambio todavía privado');
    $publicacion->delete();
    $this->get($url)->assertSee('Anuario del repositorio');
    app(PublicadorMicrositio::class)->publicar('publicaciones');
    $this->get($url)->assertDontSee('Anuario del repositorio')->assertSee('Todavía no hay publicaciones disponibles.');
});

test('un visitante sin permisos no administra publicaciones', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Member]));
    $this->get('/gestion/micrositio-publicaciones')->assertForbidden();
});

test('el documento publicado permite descarga y vista previa PDF sin exponer borradores', function () {
    Storage::fake('local');
    $path = 'micrositio/publicaciones/anuario.pdf';
    Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    $publicacion = MicrositioPublicacion::create([
        'tipo' => 'Anuario', 'titulo' => 'Anuario con PDF', 'fecha' => '2025-02-01',
        'resumen' => 'Documento del repositorio', 'archivo_path' => $path, 'nombre_archivo' => 'Anuario.pdf',
        'es_ejemplo' => false, 'orden' => 0,
    ]);
    $url = route('micrositio.publicaciones.archivo', ['publicacion' => $publicacion->id]);
    $this->get($url)->assertNotFound();
    $this->get($url.'?preview=1&inline=1')->assertNotFound();
    app(PublicadorMicrositio::class)->publicar('publicaciones');
    $this->get($url)->assertDownload('Anuario.pdf');
    $this->get($url.'?inline=1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('micrositio.publicaciones'))->assertSee('data-pdf-preview', false)->assertSee('2025-02-01');
    Storage::disk('local')->put($path, '<script>invalido</script>');
    $this->get($url.'?inline=1')->assertStatus(415);
});

test('el formulario rechaza tipos desconocidos de publicacion', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));
    Livewire::test(ListMicrositioPublicaciones::class)
        ->callAction('create', data: [
            'tipo' => 'Desconocido', 'titulo' => 'Documento', 'fecha' => '2026-11-12',
            'resumen' => 'Resumen', 'orden' => 0, 'es_ejemplo' => false,
        ])->assertHasActionErrors(['tipo']);
});
