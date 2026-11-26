<?php

namespace App\Services\Analitica;

class DiccionarioAnalitica
{
    /**
     * @return array{vistas: list<array<string, mixed>>, pendientes: list<array{nombre: string, descripcion: string}>}
     */
    public static function todo(): array
    {
        static $diccionario = null;

        if ($diccionario === null) {
            /** @var array{vistas: list<array<string, mixed>>, pendientes: list<array{nombre: string, descripcion: string}>} $cargado */
            $cargado = require database_path('analitica/diccionario.php');
            $diccionario = $cargado;
        }

        return $diccionario;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function vistas(): array
    {
        return self::todo()['vistas'];
    }

    /**
     * @return list<array{nombre: string, descripcion: string}>
     */
    public static function pendientes(): array
    {
        return self::todo()['pendientes'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function vista(string $nombre): ?array
    {
        foreach (self::vistas() as $vista) {
            if ($vista['nombre'] === $nombre) {
                return $vista;
            }
        }

        return null;
    }
}
