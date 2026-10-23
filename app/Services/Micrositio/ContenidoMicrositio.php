<?php

namespace App\Services\Micrositio;

use Illuminate\Support\Facades\Storage;

class ContenidoMicrositio
{
    public function __construct(private readonly PublicadorMicrositio $publicador) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(bool $preview): array
    {
        $leer = $preview
            ? fn (string $seccion): array => $this->publicador->registrosBorrador($seccion)
            : fn (string $seccion): array => $this->publicador->registrosPublicados($seccion);

        $hero = $leer('hero')[0]['atributos'] ?? [];
        $pie = $leer('pie')[0]['atributos'] ?? [];

        return [
            'preview' => $preview,
            'hero' => [
                'titulo' => (string) ($hero['titulo'] ?? ''),
                'subtitulo' => (string) ($hero['subtitulo'] ?? ''),
                'banner_url' => $this->urlBanner($preview),
            ],
            'cifras' => array_map(fn (array $registro): array => [
                'valor' => (string) $registro['atributos']['valor'],
                'descripcion' => (string) $registro['atributos']['descripcion'],
                'fuente' => (string) $registro['atributos']['fuente'],
                'corte' => $this->fecha((string) ($registro['atributos']['fecha_corte'] ?? '')),
                'es_ejemplo' => (bool) $registro['atributos']['es_ejemplo'],
            ], $leer('cifras')),
            'temas' => array_map(fn (array $registro): array => [
                'slug' => (string) $registro['atributos']['slug'],
                'nombre' => (string) $registro['atributos']['nombre'],
            ], $leer('temas')),
            'indicadores' => array_map(fn (array $registro): array => [
                'id' => $registro['id'],
                'codigo' => (string) $registro['atributos']['codigo'],
                'nombre' => (string) $registro['atributos']['nombre'],
                'etiqueta' => $registro['atributos']['codigo'].' — '.$registro['atributos']['nombre'],
                'tema' => (string) ($registro['atributos']['tema_slug'] ?? ''),
                'descripcion' => (string) $registro['atributos']['descripcion'],
                'es_publico' => (bool) $registro['atributos']['es_publico'],
                'es_ejemplo' => (bool) $registro['atributos']['es_ejemplo'],
                'dashboard_uuid' => $registro['atributos']['dashboard_uuid'] ?: null,
                'conjunto_datos' => $registro['atributos']['conjunto_datos'] ?: null,
            ], $leer('indicadores')),
            'publicaciones' => array_map(fn (array $registro): array => [
                'id' => $registro['id'],
                'tipo' => (string) $registro['atributos']['tipo'],
                'titulo' => (string) $registro['atributos']['titulo'],
                'fecha' => (string) ($registro['atributos']['fecha'] ?? ''),
                'resumen' => (string) $registro['atributos']['resumen'],
                'es_ejemplo' => (bool) $registro['atributos']['es_ejemplo'],
                'archivo_url' => filled($registro['atributos']['archivo_path'] ?? null)
                    ? route('micrositio.publicaciones.archivo', ['publicacion' => $registro['id'], 'preview' => $preview ? 1 : null])
                    : null,
            ], $leer('publicaciones')),
            'menu' => array_map(fn (array $registro): array => [
                'texto' => (string) $registro['atributos']['texto'],
                'url' => (string) $registro['atributos']['url'],
            ], $leer('menu')),
            'contacto' => [
                'direccion' => (string) ($pie['direccion'] ?? ''),
                'horario' => (string) ($pie['horario'] ?? ''),
                'conmutador' => (string) ($pie['conmutador'] ?? ''),
                'linea_gratuita' => (string) ($pie['linea_gratuita'] ?? ''),
                'anticorrupcion' => (string) ($pie['anticorrupcion'] ?? ''),
                'correo' => (string) ($pie['correo'] ?? ''),
                'judiciales' => (string) ($pie['judiciales'] ?? ''),
            ],
        ];
    }

    public function urlBanner(bool $preview): ?string
    {
        $registros = $preview
            ? $this->publicador->registrosBorrador('hero')
            : $this->publicador->registrosPublicados('hero');
        $path = $registros[0]['atributos']['banner_path'] ?? null;

        if (! is_string($path) || $path === '' || ! $this->rutaSegura($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return route('micrositio.banner', ['preview' => $preview ? 1 : null]);
    }

    public function rutaSegura(string $path): bool
    {
        return str_starts_with($path, 'micrositio/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    private function fecha(string $fecha): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            return $fecha;
        }

        return $partes[3].'/'.$partes[2].'/'.$partes[1];
    }
}
