<?php

namespace App\Services\Micrositio\Mapa;

use App\Models\MicrositioMapaCapa;
use App\Models\MicrositioMapaSincronizacion;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SincronizarCapasMapaInicio
{
    public const FUENTES = ['petroleo', 'agricultura'];

    public function __construct(
        private readonly Factory $http,
        private readonly FuenteJsonMapaInicio $archivos,
    ) {}

    /** @return array<string, array{estado: string, periodo?: string, error?: string}> */
    public function sincronizar(?string $fuente = null): array
    {
        $this->asegurarPoblacion();
        $fuentes = $fuente === null || $fuente === 'todas' ? self::FUENTES : [$fuente];
        $resultados = [];

        foreach ($fuentes as $nombre) {
            if (! in_array($nombre, self::FUENTES, true)) {
                throw new RuntimeException('La fuente solicitada no existe: '.$nombre.'.');
            }

            $resultados[$nombre] = $this->ejecutar($nombre);
        }

        return $resultados;
    }

    public function asegurarPoblacion(): MicrositioMapaCapa
    {
        $existente = MicrositioMapaCapa::query()->where('codigo', 'POB-2026')->first();
        if ($existente !== null) {
            return $existente;
        }

        $capa = $this->archivos->capa('POB-2026');
        if ($capa === null) {
            throw new RuntimeException('No se encontró la proyección DANE 2026 usada para iniciar el mapa.');
        }

        return $this->guardarCapa([
            ...$capa,
            'rotulo_corto' => 'Población 2026',
            'unidad_etiqueta' => 'Habitantes',
            'periodo_orden' => 202600,
            'fuente_dataset_id' => null,
            'fuente_actualizada_en' => Carbon::parse('2025-07-30', 'America/Bogota'),
            'sincronizada_en' => now(),
            'estado' => 'lista',
            'orden' => 10,
        ]);
    }

    /** @return array{estado: string, periodo?: string, error?: string} */
    private function ejecutar(string $fuente): array
    {
        $registro = MicrositioMapaSincronizacion::query()->create([
            'fuente' => $fuente,
            'capa_codigo' => config("micrositio_mapa.{$fuente}.codigo"),
            'estado' => 'ejecutando',
            'iniciada_en' => now(),
        ]);

        try {
            $resultado = $fuente === 'petroleo' ? $this->petroleo() : $this->agricultura();
            $registro->update([
                'estado' => 'exitosa',
                'periodo' => $resultado['capa']['periodo'],
                'registros_recibidos' => $resultado['recibidos'],
                'registros_procesados' => 29,
                'checksum' => $resultado['modelo']->checksum,
                'finalizada_en' => now(),
            ]);

            return ['estado' => 'exitosa', 'periodo' => $resultado['capa']['periodo']];
        } catch (Throwable $exception) {
            report($exception);
            $mensaje = Str::limit($exception->getMessage(), 1900, '');
            $registro->update(['estado' => 'fallida', 'error' => $mensaje, 'finalizada_en' => now()]);
            MicrositioMapaCapa::query()
                ->where('codigo', config("micrositio_mapa.{$fuente}.codigo"))
                ->update(['estado' => 'error', 'ultimo_error' => $mensaje]);

            return ['estado' => 'fallida', 'error' => $mensaje];
        }
    }

    /** @return array{capa: array<string, mixed>, modelo: MicrositioMapaCapa, recibidos: int} */
    private function petroleo(): array
    {
        $configuracion = config('micrositio_mapa.petroleo');
        $dataset = (string) $configuracion['dataset_id'];
        $metadatos = $this->metadatos($dataset, [
            'vigencia', 'mes', 'nombre_mes', 'departamento', 'municipio', 'produccion_bls',
        ]);
        $periodos = $this->request()->get("resource/{$dataset}.json", [
            '$select' => 'vigencia, mes, nombre_mes',
            '$where' => 'departamento="META"',
            '$group' => 'vigencia, mes, nombre_mes',
            '$order' => 'vigencia DESC, mes DESC',
            '$limit' => 1,
        ])->throw()->json();
        $periodo = is_array($periodos) ? ($periodos[0] ?? null) : null;
        $anio = (int) ($periodo['vigencia'] ?? 0);
        $mes = (int) ($periodo['mes'] ?? 0);
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            throw new RuntimeException('ANH no devolvió un periodo de producción válido para el Meta.');
        }

        $filas = $this->request()->get("resource/{$dataset}.json", [
            '$select' => 'municipio, produccion_bls',
            '$where' => sprintf('departamento="META" and vigencia="%d" and mes="%d"', $anio, $mes),
            '$limit' => 5000,
        ])->throw()->json();
        if (! is_array($filas) || $filas === []) {
            throw new RuntimeException('ANH no devolvió producción para el último periodo disponible.');
        }

        $municipios = $this->municipios();
        $porNombre = collect($municipios)->mapWithKeys(fn (array $municipio): array => [
            $this->normalizarNombre($municipio['municipio']) => $municipio['divipola'],
        ]);
        $totales = [];
        $desconocidos = [];
        foreach ($filas as $fila) {
            $nombre = $this->normalizarNombre((string) ($fila['municipio'] ?? ''));
            $divipola = $porNombre->get($nombre);
            if ($divipola === null) {
                $desconocidos[] = (string) ($fila['municipio'] ?? 'sin nombre');

                continue;
            }
            $valor = $this->numero($fila['produccion_bls'] ?? null);
            $totales[$divipola] = ($totales[$divipola] ?? 0) + $valor;
        }
        if ($desconocidos !== []) {
            throw new RuntimeException('ANH reportó municipios que no pudieron homologarse: '.implode(', ', array_unique($desconocidos)).'.');
        }

        $nombreMes = ucfirst(mb_strtolower((string) ($periodo['nombre_mes'] ?? Carbon::create($anio, $mes)->translatedFormat('F'))));
        $capa = [
            'codigo' => $configuracion['codigo'],
            'nombre' => 'Producción fiscalizada de petróleo',
            'rotulo_corto' => sprintf('Petróleo %02d/%d', $mes, $anio),
            'unidad' => 'barriles_mes',
            'unidad_etiqueta' => 'Barriles/mes',
            'periodo' => $nombreMes.' '.$anio,
            'periodo_orden' => ($anio * 100) + $mes,
            'fuente' => 'ANH — Producción Fiscalizada de Crudo Consolidada',
            'fuente_dataset_id' => $dataset,
            'fuente_url' => $configuracion['url'],
            'fuente_actualizada_en' => $metadatos['actualizada_en'],
            'sincronizada_en' => now(),
            'polaridad' => 'neutral',
            'valor_departamental' => round(array_sum($totales), 2),
            'nota' => 'Escala descriptiva. Un valor alto indica mayor volumen producido, no una valoración favorable o desfavorable.',
            'valores' => $this->valoresCompletos($municipios, $totales, 2),
            'decimales' => 0,
            'estado' => 'lista',
            'orden' => 20,
        ];

        return ['capa' => $capa, 'modelo' => $this->guardarCapa($capa), 'recibidos' => count($filas)];
    }

    /** @return array{capa: array<string, mixed>, modelo: MicrositioMapaCapa, recibidos: int} */
    private function agricultura(): array
    {
        $configuracion = config('micrositio_mapa.agricultura');
        $dataset = (string) $configuracion['dataset_id'];
        $metadatos = $this->metadatos($dataset, [
            'c_digo_dane_departamento', 'c_digo_dane_municipio', 'municipio', 'a_o', 'producci_n',
        ]);
        $periodos = $this->request()->get("resource/{$dataset}.json", [
            '$select' => 'max(a_o) as periodo',
            '$where' => 'c_digo_dane_departamento="50"',
            '$limit' => 1,
        ])->throw()->json();
        $anio = (int) (is_array($periodos) ? ($periodos[0]['periodo'] ?? 0) : 0);
        if ($anio < 2000) {
            throw new RuntimeException('UPRA no devolvió una vigencia agrícola válida para el Meta.');
        }

        $filas = $this->request()->get("resource/{$dataset}.json", [
            '$select' => 'c_digo_dane_municipio, municipio, sum(producci_n::number) as valor',
            '$where' => sprintf('c_digo_dane_departamento="50" and a_o="%d"', $anio),
            '$group' => 'c_digo_dane_municipio, municipio',
            '$order' => 'c_digo_dane_municipio',
            '$limit' => 100,
        ])->throw()->json();
        if (! is_array($filas) || $filas === []) {
            throw new RuntimeException('UPRA no devolvió producción agrícola para la última vigencia.');
        }

        $municipios = $this->municipios();
        $codigos = collect($municipios)->pluck('divipola')->flip();
        $totales = [];
        foreach ($filas as $fila) {
            $divipola = str_pad((string) preg_replace('/\D/', '', (string) ($fila['c_digo_dane_municipio'] ?? '')), 5, '0', STR_PAD_LEFT);
            if (! $codigos->has($divipola)) {
                throw new RuntimeException('UPRA reportó un DIVIPOLA que no pertenece al Meta: '.$divipola.'.');
            }
            $totales[$divipola] = $this->numero($fila['valor'] ?? null);
        }

        $capa = [
            'codigo' => $configuracion['codigo'],
            'nombre' => 'Producción agrícola total',
            'rotulo_corto' => 'Producción agrícola '.$anio,
            'unidad' => 'toneladas',
            'unidad_etiqueta' => 'Toneladas',
            'periodo' => (string) $anio,
            'periodo_orden' => $anio * 100,
            'fuente' => 'UPRA — Evaluaciones Agropecuarias Municipales (EVA)',
            'fuente_dataset_id' => $dataset,
            'fuente_url' => $configuracion['url'],
            'fuente_actualizada_en' => $metadatos['actualizada_en'],
            'sincronizada_en' => now(),
            'polaridad' => 'neutral',
            'valor_departamental' => round(array_sum($totales), 2),
            'nota' => 'Suma de la producción reportada para todos los cultivos. Las dos últimas vigencias pueden recibir ajustes de UPRA.',
            'valores' => $this->valoresCompletos($municipios, $totales, 2),
            'decimales' => 0,
            'estado' => 'lista',
            'orden' => 30,
        ];

        return ['capa' => $capa, 'modelo' => $this->guardarCapa($capa), 'recibidos' => count($filas)];
    }

    /** @param list<string> $requeridos @return array{actualizada_en: Carbon|null} */
    private function metadatos(string $dataset, array $requeridos): array
    {
        $metadatos = $this->request()->get("api/views/{$dataset}")->throw()->json();
        $campos = collect($metadatos['columns'] ?? [])->pluck('fieldName')->filter()->all();
        $faltantes = array_values(array_diff($requeridos, $campos));
        if ($faltantes !== []) {
            throw new RuntimeException('La estructura oficial cambió; faltan los campos: '.implode(', ', $faltantes).'.');
        }

        $actualizada = isset($metadatos['rowsUpdatedAt']) ? Carbon::createFromTimestamp((int) $metadatos['rowsUpdatedAt']) : null;

        return ['actualizada_en' => $actualizada];
    }

    /** @param array<string, mixed> $capa */
    private function guardarCapa(array $capa): MicrositioMapaCapa
    {
        $existente = MicrositioMapaCapa::query()->where('codigo', $capa['codigo'])->first();
        if ($existente !== null && (int) $existente->periodo_orden > (int) $capa['periodo_orden']) {
            throw new RuntimeException('La fuente devolvió un periodo anterior al que ya está publicado.');
        }

        $checksum = hash('sha256', json_encode($capa['valores'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $atributos = [
            'nombre' => $capa['nombre'],
            'rotulo_corto' => $capa['rotulo_corto'],
            'unidad' => $capa['unidad'],
            'unidad_etiqueta' => $capa['unidad_etiqueta'],
            'periodo' => $capa['periodo'],
            'periodo_orden' => $capa['periodo_orden'],
            'fuente' => $capa['fuente'],
            'fuente_dataset_id' => $capa['fuente_dataset_id'] ?? null,
            'fuente_url' => $capa['fuente_url'],
            'fuente_actualizada_en' => $capa['fuente_actualizada_en'] ?? null,
            'sincronizada_en' => $capa['sincronizada_en'] ?? now(),
            'polaridad' => $capa['polaridad'] ?? 'neutral',
            'decimales' => $capa['decimales'] ?? 0,
            'valor_departamental' => $capa['valor_departamental'] ?? null,
            'valor_anterior_departamental' => $capa['valor_anterior_departamental'] ?? null,
            'nota' => $capa['nota'] ?? null,
            'valores' => $capa['valores'],
            'checksum' => $checksum,
            'estado' => $capa['estado'] ?? 'lista',
            'ultimo_error' => null,
            'activa' => true,
            'orden' => $capa['orden'] ?? 0,
        ];

        return DB::transaction(fn (): MicrositioMapaCapa => MicrositioMapaCapa::query()->updateOrCreate(
            ['codigo' => $capa['codigo']],
            $atributos,
        ));
    }

    /** @return list<array{divipola: string, municipio: string}> */
    private function municipios(): array
    {
        $features = $this->archivos->geometriaMunicipios()['features'] ?? [];
        $municipios = collect($features)->map(fn (array $feature): array => [
            'divipola' => (string) ($feature['properties']['divipola'] ?? ''),
            'municipio' => (string) ($feature['properties']['municipio'] ?? ''),
        ])->filter(fn (array $municipio): bool => preg_match('/^50\d{3}$/', $municipio['divipola']) === 1 && $municipio['municipio'] !== '')
            ->sortBy('divipola')->values()->all();

        if (count($municipios) !== 29) {
            throw new RuntimeException('El catálogo local no contiene los 29 municipios válidos del Meta.');
        }

        return $municipios;
    }

    /** @param list<array{divipola: string, municipio: string}> $municipios @param array<string, float|int> $totales */
    private function valoresCompletos(array $municipios, array $totales, int $decimales): array
    {
        return array_map(function (array $municipio) use ($totales, $decimales): array {
            $valor = round((float) ($totales[$municipio['divipola']] ?? 0), $decimales);
            if (! is_finite($valor) || $valor < 0) {
                throw new RuntimeException('La fuente devolvió un valor municipal inválido.');
            }

            return [...$municipio, 'valor' => $valor];
        }, $municipios);
    }

    private function numero(mixed $valor): float
    {
        $normalizado = str_replace([',', ' '], '', (string) $valor);
        if ($normalizado === '' || ! is_numeric($normalizado)) {
            throw new RuntimeException('La fuente devolvió un valor no numérico.');
        }
        $numero = (float) $normalizado;
        if (! is_finite($numero) || $numero < 0) {
            throw new RuntimeException('La fuente devolvió un valor negativo o fuera de rango.');
        }

        return $numero;
    }

    private function normalizarNombre(string $nombre): string
    {
        $normalizado = Str::of(Str::ascii($nombre))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

        return match ($normalizado) {
            'castilla nueva' => 'castilla la nueva',
            'vista hermosa' => 'vistahermosa',
            default => $normalizado,
        };
    }

    private function request(): PendingRequest
    {
        $request = $this->http->baseUrl(rtrim((string) config('micrositio_mapa.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(45)
            ->retry(2, 750, throw: false);

        if ($token = config('micrositio_mapa.app_token')) {
            $request->withHeaders(['X-App-Token' => $token]);
        }

        return $request;
    }
}
