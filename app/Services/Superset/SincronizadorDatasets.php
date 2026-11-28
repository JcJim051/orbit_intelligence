<?php

namespace App\Services\Superset;

use App\Services\Analitica\DiccionarioAnalitica;
use RuntimeException;

class SincronizadorDatasets
{
    public function __construct(private readonly SupersetClient $superset) {}

    /**
     * @return array{creados: list<string>, actualizados: list<string>}
     */
    public function sincronizar(): array
    {
        $databaseId = $this->databaseId();
        $existentes = $this->datasets();
        $creados = [];
        $actualizados = [];

        foreach (DiccionarioAnalitica::vistas() as $vista) {
            $nombre = (string) $vista['nombre'];
            $id = $existentes[$nombre] ?? null;

            if ($id === null) {
                $this->superset->request('post', '/api/v1/dataset/', [
                    'database' => $databaseId,
                    'schema' => 'analitica',
                    'table_name' => $nombre,
                ]);
                $creados[] = $nombre;

                continue;
            }

            $this->superset->request('put', '/api/v1/dataset/'.$id.'/refresh');
            $actualizados[] = $nombre;
        }

        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    public function resolverDataset(string $nombre): int
    {
        $existente = $this->datasets()[$nombre] ?? null;

        if ($existente !== null) {
            $this->superset->request('put', '/api/v1/dataset/'.$existente.'/refresh');

            return $existente;
        }

        $respuesta = $this->superset->request('post', '/api/v1/dataset/', [
            'database' => $this->databaseId(),
            'schema' => 'analitica',
            'table_name' => $nombre,
        ]);
        $id = $respuesta['id'] ?? $respuesta['result']['id'] ?? null;

        if (! is_numeric($id)) {
            throw new RuntimeException('Superset creó el dataset sin devolver un identificador válido.');
        }

        return (int) $id;
    }

    private function databaseId(): int
    {
        $configurado = config('superset.database_id');

        if (is_numeric($configurado) && (int) $configurado > 0) {
            return (int) $configurado;
        }

        $respuesta = $this->superset->request('get', '/api/v1/database/?q=(page_size:100)');
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
    private function datasets(): array
    {
        $respuesta = $this->superset->request('get', '/api/v1/dataset/?q=(page_size:500)');
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
}
