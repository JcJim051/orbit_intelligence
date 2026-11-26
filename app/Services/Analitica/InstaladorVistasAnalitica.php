<?php

namespace App\Services\Analitica;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class InstaladorVistasAnalitica
{
    public function instalar(): void
    {
        $this->eliminarVistas();

        foreach (DiccionarioAnalitica::vistas() as $vista) {
            $nombre = $this->nombre((string) $vista['nombre']);
            DB::statement('CREATE VIEW '.EsquemaAnalitica::tabla($nombre).' AS '.$this->sql((string) $vista['sql']));

            if (EsquemaAnalitica::pgsql()) {
                DB::statement(
                    'COMMENT ON VIEW '.EsquemaAnalitica::tabla($nombre).' IS '.$this->texto((string) $vista['descripcion']),
                );
            }
        }

        $this->publicarDiccionario();
    }

    public function eliminarVistas(): void
    {
        $nombres = array_map(
            fn (array $vista): string => $this->nombre((string) $vista['nombre']),
            DiccionarioAnalitica::vistas(),
        );

        foreach (array_reverse($nombres) as $nombre) {
            DB::statement('DROP VIEW IF EXISTS '.EsquemaAnalitica::tabla($nombre));
        }
    }

    public function publicarDiccionario(): void
    {
        $tabla = EsquemaAnalitica::tabla('diccionario');

        if (! Schema::hasTable($tabla)) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('tipo', 20);
                $table->string('vista')->nullable();
                $table->string('columna')->nullable();
                $table->string('dominio')->nullable();
                $table->text('descripcion');
                $table->text('fuente')->nullable();
                $table->text('corte')->nullable();
            });
        }

        DB::table($tabla)->delete();

        $filas = [];

        foreach (DiccionarioAnalitica::vistas() as $vista) {
            $filas[] = [
                'tipo' => 'vista',
                'vista' => $vista['nombre'],
                'columna' => null,
                'dominio' => $vista['dominio'],
                'descripcion' => $vista['descripcion'],
                'fuente' => $vista['fuente'],
                'corte' => $vista['corte'],
            ];

            foreach ($vista['columnas'] as $columna) {
                $filas[] = [
                    'tipo' => 'columna',
                    'vista' => $vista['nombre'],
                    'columna' => $columna['nombre'],
                    'dominio' => $vista['dominio'],
                    'descripcion' => $columna['descripcion'],
                    'fuente' => $columna['fuente'],
                    'corte' => null,
                ];
            }
        }

        foreach (DiccionarioAnalitica::pendientes() as $pendiente) {
            $filas[] = [
                'tipo' => 'pendiente',
                'vista' => $pendiente['nombre'],
                'columna' => null,
                'dominio' => null,
                'descripcion' => $pendiente['descripcion'],
                'fuente' => null,
                'corte' => null,
            ];
        }

        foreach (array_chunk($filas, 100) as $lote) {
            DB::table($tabla)->insert($lote);
        }
    }

    public function sql(string $plantilla): string
    {
        $reemplazos = ['{{pasiva_rubro}}' => EsquemaAnalitica::tabla('pasiva_rubro')];

        foreach (DiccionarioAnalitica::vistas() as $vista) {
            $nombre = $this->nombre((string) $vista['nombre']);
            $reemplazos['{{'.$nombre.'}}'] = EsquemaAnalitica::tabla($nombre);
        }

        return strtr($plantilla, $reemplazos);
    }

    private function nombre(string $nombre): string
    {
        if (preg_match('/^[a-z0-9_]+$/', $nombre) !== 1) {
            throw new InvalidArgumentException('Nombre de vista de analitica inválido.');
        }

        return $nombre;
    }

    private function texto(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
