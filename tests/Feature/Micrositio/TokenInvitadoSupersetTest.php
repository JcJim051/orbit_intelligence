<?php

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\MicrositioIndicador;
use App\Models\User;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config([
        'superset.url' => 'http://superset.test',
        'superset.username' => 'servicio',
        'superset.password' => 'secreto-de-servicio',
        'superset.guest_role' => 'Public',
    ]);

    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/api/v1/security/login')) {
            return Http::response(['access_token' => 'acceso-servicio']);
        }

        return Http::response(['token' => 'token-invitado']);
    });
});

function indicadorPublicado(array $atributos = []): MicrositioIndicador
{
    $indicador = MicrositioIndicador::factory()->create(array_merge([
        'codigo' => 'FP-01',
        'nombre' => 'Ejecución de la pasiva por fuente y sector',
        'es_publico' => true,
        'es_ejemplo' => false,
        'dashboard_uuid' => '11111111-1111-4111-8111-111111111111',
    ], $atributos));

    app(PublicadorMicrositio::class)->publicar('temas');
    app(PublicadorMicrositio::class)->publicar('indicadores');

    return $indicador;
}

test('un visitante recibe el token de un indicador publicado y público', function () {
    indicadorPublicado();

    $this->getJson(route('micrositio.indicadores.token', 'FP-01'))
        ->assertOk()
        ->assertJsonPath('token', 'token-invitado')
        ->assertJsonPath('uuid', '11111111-1111-4111-8111-111111111111')
        ->assertJsonPath('rls', [])
        ->assertDontSee('secreto-de-servicio');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'guest_token')) {
            return false;
        }

        return $request->data()['resources'][0]['id'] === '11111111-1111-4111-8111-111111111111'
            && $request->data()['rls'] === []
            && $request->data()['user']['username'] === 'publico_micrositio';
    });
});

test('un visitante recibe 403 si el indicador no está publicado', function () {
    MicrositioIndicador::factory()->create([
        'codigo' => 'FP-01',
        'es_publico' => true,
        'dashboard_uuid' => '11111111-1111-4111-8111-111111111111',
    ]);

    $this->getJson(route('micrositio.indicadores.token', 'FP-01'))->assertForbidden();
    Http::assertNothingSent();
});

test('un visitante recibe 403 si el indicador publicado no es público', function () {
    indicadorPublicado(['es_publico' => false]);

    $this->getJson(route('micrositio.indicadores.token', 'FP-01'))->assertForbidden();
});

test('un visitante recibe 403 si el indicador público no tiene tablero', function () {
    indicadorPublicado(['dashboard_uuid' => null]);

    $this->getJson(route('micrositio.indicadores.token', 'FP-01'))->assertForbidden();
});

test('un visitante no puede inyectar un sector en el token público', function () {
    indicadorPublicado();

    $this->getJson(route('micrositio.indicadores.token', [
        'codigo' => 'FP-01',
        'sector' => "22' OR '1'='1",
    ]))->assertOk()->assertJsonPath('rls', []);
});

test('el token autenticado restringe por la dependencia del usuario', function () {
    indicadorPublicado();
    $dependencia = Dependencia::factory()->create([
        'codigo' => '22',
        'nombre' => "O'Brien",
    ]);
    $usuario = User::factory()->create(['role' => UserRole::Member]);
    $usuario->dependencias()->attach($dependencia);

    $respuesta = $this->actingAs($usuario)->getJson(route('micrositio.tableros.token', 'FP-01'));

    $respuesta->assertOk();
    $clausula = $respuesta->json('rls.0.clause');
    expect($clausula)->toContain("sector = '22 — O''Brien'")
        ->and($clausula)->toContain("sector LIKE '22 — %'")
        ->and($clausula)->not->toContain("OR '1'");

    Http::assertSent(function (Request $request) use ($clausula): bool {
        return str_contains($request->url(), 'guest_token')
            && $request->data()['rls'][0]['clause'] === $clausula
            && $request->data()['user']['username'] === 'usuario_siid';
    });
});

test('un usuario sin sector recibe 403 en el token autenticado', function () {
    indicadorPublicado();
    $usuario = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($usuario)->getJson(route('micrositio.tableros.token', 'FP-01'))->assertForbidden();
});

test('un administrador recibe el token sin RLS y no puede forzar otro sector por texto', function () {
    indicadorPublicado(['es_publico' => false]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->getJson(route('micrositio.tableros.token', ['codigo' => 'FP-01', 'sector' => '99 — Inventado']))
        ->assertOk()
        ->assertJsonPath('rls', []);
});

test('un usuario no puede pedir el token del sector de otra dependencia', function () {
    indicadorPublicado();
    $propia = Dependencia::factory()->create(['codigo' => '22', 'nombre' => 'Educación']);
    $ajena = Dependencia::factory()->create(['codigo' => '19', 'nombre' => 'Salud']);
    $usuario = User::factory()->create(['role' => UserRole::Member]);
    $usuario->dependencias()->attach($propia);

    $this->actingAs($usuario)
        ->getJson(route('micrositio.tableros.token', ['codigo' => 'FP-01', 'dependencia_id' => $ajena->id]))
        ->assertForbidden();
});

test('responde 503 si Superset no está configurado', function () {
    config(['superset.url' => '']);
    indicadorPublicado();

    $this->getJson(route('micrositio.indicadores.token', 'FP-01'))
        ->assertStatus(503)
        ->assertDontSee('secreto-de-servicio');
});
