<?php

use App\Enums\UserRole;
use App\Filament\Resources\MicrositioPoliticaResource\Pages\ListMicrositioPoliticas;
use App\Models\MicrositioPolitica;
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

test('el administrador crea un borrador y lo publica desde el panel', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));
    $this->get('/gestion/micrositio-politicas')->assertOk();
    Livewire::test(ListMicrositioPoliticas::class)
        ->callAction('create', data: [
            'nombre' => 'Política demostrativa',
            'slug' => 'politica-demo',
            'descripcion' => 'Descripción para verificar el flujo.',
            'objetivo' => 'Objetivo de prueba.',
            'orden' => 0,
            'es_ejemplo' => true,
        ])->assertHasNoActionErrors();
    $this->assertDatabaseHas('micrositio_politicas', ['slug' => 'politica-demo']);
    $url = route('micrositio.politicas', ['politica' => 'politica-demo']);
    $this->get($url)->assertNotFound();
    $this->get($url.'&preview=1')->assertOk()->assertSee('Objetivo de prueba.');
    Livewire::test(ListMicrositioPoliticas::class)->callAction('publicar')->assertHasNoActionErrors();
    auth()->logout();
    $this->get($url)->assertOk()->assertSee('Objetivo de prueba.')->assertDontSee('Contenido de prueba');
});

test('editar conserva lo publicado y los visitantes no pueden ver el borrador', function () {
    $politica = MicrositioPolitica::factory()->create(['objetivo' => 'Objetivo publicado']);
    $publicador = app(PublicadorMicrositio::class);
    $publicador->publicar('politicas');
    $politica->update(['objetivo' => 'Objetivo aún privado']);
    $url = route('micrositio.politicas', ['politica' => $politica->slug]);
    $this->get($url.'&preview=1')->assertOk()->assertSee('Objetivo publicado')->assertDontSee('Objetivo aún privado');
    $this->actingAs(User::factory()->create(['role' => UserRole::Member]));
    $this->get($url.'&preview=1')->assertOk()->assertDontSee('Objetivo aún privado');
    $this->get('/gestion/micrositio-politicas')->assertForbidden();
    expect(auth()->user()->can('publish', MicrositioPolitica::class))->toBeFalse();
    $publicador->publicar('politicas');
    $this->get($url)->assertSee('Objetivo aún privado')->assertDontSee('Objetivo publicado');
});

test('el formulario rechaza identificadores duplicados o inválidos', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('management'));
    MicrositioPolitica::factory()->create(['slug' => 'existente']);
    foreach (['existente', '../invalido'] as $slug) {
        Livewire::test(ListMicrositioPoliticas::class)->callAction('create', data: [
            'nombre' => 'Otra política', 'slug' => $slug,
            'descripcion' => 'Descripción', 'orden' => 0, 'es_ejemplo' => true,
        ])->assertHasActionErrors(['slug']);
    }
    expect(MicrositioPolitica::count())->toBe(1);
});

test('los documentos respetan el borrador y la versión publicada', function () {
    Storage::fake('local');
    $path = 'micrositio/politicas/ordenanza.pdf';
    Storage::disk('local')->put($path, 'documento');
    $politica = MicrositioPolitica::factory()->create([
        'documentos' => [$path], 'nombres_documentos' => [$path => 'Ordenanza.pdf'],
    ]);
    $url = route('micrositio.politicas.documento', ['slug' => $politica->slug, 'indice' => 0]);
    $this->get($url)->assertNotFound();
    $this->get($url.'?preview=1')->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->get($url.'?preview=1')->assertDownload('Ordenanza.pdf');
    app(PublicadorMicrositio::class)->publicar('politicas');
    auth()->logout();
    $this->get($url)->assertDownload('Ordenanza.pdf');
    $politica->update(['documentos' => []]);
    $this->get($url)->assertDownload('Ordenanza.pdf');
    app(PublicadorMicrositio::class)->publicar('politicas');
    $this->get($url)->assertNotFound();
});

test('la vista previa sirve solo PDFs publicados y conserva la descarga', function () {
    Storage::fake('local');
    $path = 'micrositio/politicas/documento.pdf';
    Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    $politica = MicrositioPolitica::factory()->create(['documentos' => [$path], 'nombres_documentos' => [$path => 'Documento.pdf']]);
    $url = route('micrositio.politicas.documento', ['slug' => $politica->slug, 'indice' => 0]);
    $this->get($url.'?inline=1')->assertNotFound();
    app(PublicadorMicrositio::class)->publicar('politicas');
    $respuesta = $this->get($url.'?inline=1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($respuesta->headers->get('Content-Disposition'))->toStartWith('inline;');
    $this->get($url)->assertDownload('Documento.pdf');
    $this->get(route('micrositio.politicas', ['politica' => $politica->slug]))->assertSee('Vista previa')->assertSee('data-pdf-preview', false);
    Storage::disk('local')->put($path, '<script>alert(1)</script>');
    $this->get($url.'?inline=1')->assertStatus(415);
});
