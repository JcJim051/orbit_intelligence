<?php

namespace App\Services\Micrositio;

use App\Models\MicrositioCifra;
use App\Models\MicrositioHero;
use App\Models\MicrositioIndicador;
use App\Models\MicrositioMenu;
use App\Models\MicrositioPie;
use App\Models\MicrositioPolitica;
use App\Models\MicrositioPublicacion;
use App\Models\MicrositioSeccion;
use App\Models\MicrositioTema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PublicadorMicrositio
{
    /** @var array<string, array<string, mixed>|null> */
    private array $cache = [];

    /**
     * @return array<string, class-string<Model>>
     */
    public function secciones(): array
    {
        return [
            'hero' => MicrositioHero::class,
            'cifras' => MicrositioCifra::class,
            'temas' => MicrositioTema::class,
            'indicadores' => MicrositioIndicador::class,
            'publicaciones' => MicrositioPublicacion::class,
            'menu' => MicrositioMenu::class,
            'pie' => MicrositioPie::class,
            'politicas' => MicrositioPolitica::class,
        ];
    }

    public function publicar(string $seccion): void
    {
        $registros = $this->consulta($seccion)->get()->map(fn (Model $registro): array => [
            'id' => $registro->getKey(),
            'atributos' => $this->atributos($registro),
        ])->values()->all();

        MicrositioSeccion::query()->updateOrCreate(
            ['clave' => $seccion],
            [
                'publicado' => ['registros' => $registros],
                'publicado_en' => now(),
            ],
        );

        unset($this->cache[$seccion]);
    }

    public function estadoDe(string $seccion, Model $registro): string
    {
        $publicado = $this->atributosPublicados($seccion, (int) $registro->getKey());

        if ($publicado === null) {
            return 'borrador';
        }

        return $publicado == $this->atributos($registro) ? 'publicado' : 'borrador';
    }

    public function seccionPublicada(string $seccion): bool
    {
        $publicados = $this->registrosPublicados($seccion);
        $borrador = $this->consulta($seccion)->get()->map(fn (Model $registro): array => $this->atributos($registro))->values()->all();
        $vigentes = array_map(fn (array $registro): array => $registro['atributos'], $publicados);

        return $borrador == $vigentes && $publicados !== [];
    }

    /**
     * @return list<array{id: int, atributos: array<string, mixed>}>
     */
    public function registrosPublicados(string $seccion): array
    {
        $publicado = $this->payload($seccion);
        $registros = $publicado['registros'] ?? [];

        if (! is_array($registros)) {
            return [];
        }

        return array_values(array_map(function (mixed $registro) use ($seccion): array {
            $id = (int) (is_array($registro) ? ($registro['id'] ?? 0) : 0);
            $atributos = is_array($registro) && is_array($registro['atributos'] ?? null) ? $registro['atributos'] : [];

            return [
                'id' => $id,
                'atributos' => $this->normalizar($seccion, $atributos),
            ];
        }, $registros));
    }

    /**
     * @return list<array{id: int, atributos: array<string, mixed>}>
     */
    public function registrosBorrador(string $seccion): array
    {
        return $this->consulta($seccion)->get()->map(fn (Model $registro): array => [
            'id' => (int) $registro->getKey(),
            'atributos' => $this->atributos($registro),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function atributosPublicados(string $seccion, int $id): ?array
    {
        foreach ($this->registrosPublicados($seccion) as $registro) {
            if ($registro['id'] === $id) {
                return $registro['atributos'];
            }
        }

        return null;
    }

    /**
     * @return Builder<Model>
     */
    private function consulta(string $seccion): Builder
    {
        $modelo = $this->modelo($seccion);
        $consulta = $modelo::query();

        if (! in_array($seccion, ['hero', 'pie'], true)) {
            $consulta->orderBy('orden')->orderBy('id');
        }

        if ($seccion === 'indicadores') {
            $consulta->with('tema');
        }

        return $consulta;
    }

    /**
     * @return class-string<Model>
     */
    public function modelo(string $seccion): string
    {
        $modelo = $this->secciones()[$seccion] ?? null;

        if ($modelo === null) {
            throw new InvalidArgumentException('Sección de micrositio desconocida.');
        }

        return $modelo;
    }

    /**
     * @return array<string, mixed>
     */
    private function atributos(Model $registro): array
    {
        if (! method_exists($registro, 'atributosPublicables')) {
            return [];
        }

        $seccion = array_search($registro::class, $this->secciones(), true);

        return $this->normalizar(is_string($seccion) ? $seccion : '', $registro->atributosPublicables());
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function normalizar(string $seccion, array $atributos): array
    {
        $salida = [];

        foreach ($this->claves($seccion) as $clave) {
            $valor = $atributos[$clave] ?? null;
            $salida[$clave] = is_bool($valor) || $valor === null ? $valor : (is_int($valor) ? $valor : $valor);
        }

        return $salida;
    }

    /**
     * @return list<string>
     */
    private function claves(string $seccion): array
    {
        return match ($seccion) {
            'hero' => ['titulo', 'subtitulo', 'banner_path'],
            'cifras' => ['valor', 'descripcion', 'fuente', 'fecha_corte', 'es_ejemplo', 'orden'],
            'temas' => ['slug', 'nombre', 'orden'],
            'politicas' => ['slug', 'nombre', 'descripcion', 'objetivo', 'ordenanza', 'vigencia', 'responsable', 'orden', 'es_ejemplo', 'documentos', 'nombres_documentos'],
            'indicadores' => ['codigo', 'nombre', 'tema_slug', 'tema_nombre', 'dashboard_uuid', 'descripcion', 'es_publico', 'es_ejemplo', 'conjunto_datos', 'orden'],
            'publicaciones' => ['tipo', 'titulo', 'fecha', 'resumen', 'archivo_path', 'nombre_archivo', 'es_ejemplo', 'orden'],
            'menu' => ['texto', 'url', 'orden'],
            'pie' => ['direccion', 'horario', 'conmutador', 'linea_gratuita', 'anticorrupcion', 'correo', 'judiciales'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $seccion): array
    {
        if (! array_key_exists($seccion, $this->cache)) {
            $this->cache[$seccion] = MicrositioSeccion::query()->where('clave', $seccion)->first()?->publicado;
        }

        return is_array($this->cache[$seccion]) ? $this->cache[$seccion] : [];
    }
}
