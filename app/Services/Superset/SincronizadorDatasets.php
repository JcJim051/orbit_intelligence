<?php

namespace App\Services\Superset;

use App\Services\Analitica\DiccionarioAnalitica;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SincronizadorDatasets
{
    public function __construct(private readonly SupersetClient $superset) {}

    /**
     * @return array{creados: list<string>, actualizados: list<string>}
     */
    public function sincronizar(): array
    {
        $url = rtrim((string) config('superset.url'), '/');
        $token = $this->superset->tokenAcceso();
        $databaseId = $this->databaseId($url, $token);
        $existentes = $this->datasets($url, $token);
        $creados = [];
        $actualizados = [];

        foreach (DiccionarioAnalitica::vistas() as $vista) {
            $nombre = (string) $vista['nombre'];
            $id = $existentes[$nombre] ?? null;

            if ($id === null) {
                $this->enviar($url, $token, 'post', '/api/v1/dataset/', [
                    'database' => $databaseId,
                    'schema' => 'analitica',
                    'table_name' => $nombre,
                ]);
                $creados[] = $nombre;

                continue;
            }

            $this->enviar($url, $token, 'put', '/api/v1/dataset/'.$id.'/refresh', []);
            $actualizados[] = $nombre;
        }

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    private function databaseId(string $url, string $token): int
    {
        $configurado = config('superset.database_id');

        if (is_numeric($configurado) && (int) $configurado > 0) {
            return (int) $configurado;
        }

        $respuesta = $this->enviar($url, $token, 'get', '/api/v1/database/?q=(page_size:100)', []);
        $bases = $respuesta['result'] ?? [];

        if (! is_array($bases) || $bases === []) {
            throw new RuntimeException('Superset no tiene una base de datos registrada. Defina SUPERSET_DATABASE_ID.');
        }

        if (count($bases) === 1 && isset($bases[0]['id'])) {
            return (int) $bases[0]['id'];
        }

        throw new RuntimeException('Hay varias bases en Superset. Defina SUPERSET_DATABASE_ID con la que apunta al SIID.');
    }

    /**
     * @return array<string, int>
     */
    private function datasets(string $url, string $token): array
    {
        $respuesta = $this->enviar($url, $token, 'get', '/api/v1/dataset/?q=(page_size:500)', []);
        $mapa = [];

        foreach ($respuesta['result'] ?? [] as $dataset) {
            if (! is_array($dataset)) {
                continue;
            }

            $esquema = (string) ($dataset['schema'] ?? '');
            $tabla = (string) ($dataset['table_name'] ?? '');

            if ($esquema === 'analitica' && $tabla !== '' && isset($dataset['id'])) {
                $mapa[$tabla] = (int) $dataset['id'];
            }
        }

        return $mapa;
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     * @return array<string, mixed>
     */
    private function enviar(string $url, string $token, string $metodo, string $ruta, array $cuerpo): array
    {
        try {
            $peticion = Http::timeout(20)
                ->retry(2, 200, fn (mixed $excepcion): bool => $excepcion instanceof ConnectionException)
                ->acceptJson()
                ->withToken($token);

            $respuesta = $metodo === 'get'
                ? $peticion->get($url.$ruta)
                : $peticion->{$metodo}($url.$ruta, $cuerpo);

            $respuesta->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('No fue posible actualizar los datasets de Superset.', 0, $exception);
        }

        $json = $respuesta->json();

        return is_array($json) ? $json : [];
    }
}
