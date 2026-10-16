<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Services\InteligenciaGeografica\AlmacenCapa;
use App\Services\InteligenciaGeografica\ArcGis\ClienteArcGis;
use App\Services\InteligenciaGeografica\PaqueteGeodatabase;
use Illuminate\Http\Client\ConnectionException;
use Tests\TestCase;
use Throwable;

class DescargaCapasHttpTest extends TestCase
{
    public function test_layer_downloads_use_guzzle_timeouts_on_the_real_handler(): void
    {
        config([
            'inteligencia.http.connect_timeout' => 1,
            'inteligencia.http.timeout' => 1,
            'inteligencia.http.reintentos' => 0,
            'inteligencia.http.reintento_ms' => 0,
            'inteligencia.http.paquete_timeout' => 1,
        ]);
        $cliente = app(ClienteArcGis::class);

        $this->assertRealHandler(fn () => $cliente->json('http://127.0.0.1:9/capa?f=json'));
        $this->assertRealHandler(function () use ($cliente): void {
            iterator_to_array($cliente->paginas('http://127.0.0.1:9/capa', '1=1', null, null, 'OBJECTID', 1));
        });
        $this->assertRealHandler(function (): void {
            app(PaqueteGeodatabase::class)->volcar(
                'http://127.0.0.1:9/paquete.zip',
                ['R_TERRENO'],
                $this->createMock(AlmacenCapa::class),
            );
        });
    }

    private function assertRealHandler(callable $accion): void
    {
        try {
            $accion();
            $this->fail('La conexión debía fallar.');
        } catch (Throwable $excepcion) {
            $this->assertStringNotContainsString('CURLOPT_CONNECTTIMEOUT', $excepcion->getMessage());
            $this->assertStringNotContainsString('CURLOPT_TIMEOUT', $excepcion->getMessage());
            $this->assertInstanceOf(ConnectionException::class, $excepcion);
        }
    }
}
