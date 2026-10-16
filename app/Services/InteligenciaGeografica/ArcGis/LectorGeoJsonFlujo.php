<?php

namespace App\Services\InteligenciaGeografica\ArcGis;

use Generator;
use RuntimeException;

/**
 * Lee un FeatureCollection desde disco, una entidad a la vez.
 */
class LectorGeoJsonFlujo
{
    public function excedio(string $archivo): bool
    {
        $manejador = fopen($archivo, 'rb');

        if ($manejador === false) {
            return false;
        }

        $cola = '';

        while (! feof($manejador)) {
            $bloque = fread($manejador, 65536);

            if (! is_string($bloque) || $bloque === '') {
                break;
            }

            $texto = $cola.$bloque;

            if (preg_match('/"exceededTransferLimit"\s*:\s*true/', $texto) === 1) {
                fclose($manejador);

                return true;
            }

            $cola = substr($texto, -48);
        }

        fclose($manejador);

        return false;
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function features(string $archivo): Generator
    {
        $manejador = fopen($archivo, 'rb');

        if ($manejador === false) {
            throw new RuntimeException('No se pudo leer la respuesta de la fuente.');
        }

        $enFeatures = false;
        $esperandoCorchete = false;
        $profundidad = 0;
        $enTexto = false;
        $escape = false;
        $objeto = '';

        while (! feof($manejador)) {
            $bloque = fread($manejador, 65536);

            if (! is_string($bloque) || $bloque === '') {
                break;
            }

            $largo = strlen($bloque);

            for ($indice = 0; $indice < $largo; $indice++) {
                $caracter = $bloque[$indice];

                if (! $enFeatures) {
                    if ($esperandoCorchete) {
                        if ($caracter === '[') {
                            $enFeatures = true;
                            $esperandoCorchete = false;
                        } elseif ($caracter !== ':' && ! ctype_space($caracter)) {
                            $esperandoCorchete = false;
                        }

                        continue;
                    }

                    if ($caracter === '"') {
                        $resto = substr($bloque, $indice, 10);

                        if (str_starts_with($resto, '"features"')) {
                            $esperandoCorchete = true;
                            $indice += 9;
                        }
                    }

                    continue;
                }

                if ($profundidad === 0) {
                    if ($caracter === '{') {
                        $profundidad = 1;
                        $objeto = '{';
                        $enTexto = false;
                        $escape = false;
                    } elseif ($caracter === ']') {
                        fclose($manejador);

                        return;
                    }

                    continue;
                }

                $objeto .= $caracter;

                if ($enTexto) {
                    if ($escape) {
                        $escape = false;
                    } elseif ($caracter === '\\') {
                        $escape = true;
                    } elseif ($caracter === '"') {
                        $enTexto = false;
                    }

                    continue;
                }

                if ($caracter === '"') {
                    $enTexto = true;
                } elseif ($caracter === '{') {
                    $profundidad++;
                } elseif ($caracter === '}') {
                    $profundidad--;

                    if ($profundidad === 0) {
                        $entidad = json_decode($objeto, true);
                        $objeto = '';

                        if (is_array($entidad)) {
                            yield $entidad;
                        }
                    }
                }
            }
        }

        fclose($manejador);
    }
}
