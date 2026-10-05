<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Enums\EstadoReporteProyecto;
use App\Enums\GrupoFuenteFinanciacion;
use App\Models\Actividad;
use App\Models\ActividadProgramacion;
use App\Models\AvanceFisico;
use App\Models\Dependencia;
use App\Models\EjecucionFinanciera;
use App\Models\FuenteFinanciacion;
use App\Models\MetaProducto;
use App\Models\Proyecto;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\SeguimientoCargaHistorica;
use App\Models\Techo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class CargaHistoricaMetas
{
    public const FUENTE_GENERICA_CODIGO = 'HIST_EXT';

    public const FUENTE_GENERICA_NOMBRE = 'Histórico consolidado seguimiento externo';

    private const MAX_DECIMAL_20_2 = 999999999999999999.0;

    /**
     * @return array{summary: array<string, int>, filas: list<array<string, mixed>>}
     */
    public function diagnosticar(Seguimiento $seguimiento, string $ruta, string $extension, ?string $rutaProyectos = null, ?string $extensionProyectos = null): array
    {
        $usaMatrizProyectos = $rutaProyectos !== null;
        $filas = $usaMatrizProyectos
            ? $this->leerProyectos($rutaProyectos, $extensionProyectos ?: 'xlsx')
            : $this->leer($ruta, $extension);
        $duplicados = $usaMatrizProyectos ? [] : collect($filas)
            ->filter(fn (array $fila): bool => $this->codigoPareceValido($fila['codigo_meta_producto']))
            ->groupBy(fn (array $fila): string => $fila['codigo_meta_producto'])
            ->filter(fn (Collection $grupo): bool => $grupo->count() > 1)
            ->keys()
            ->map(fn (mixed $codigo): string => (string) $codigo)
            ->all();

        $diagnosticadas = [];

        foreach ($filas as $fila) {
            $diagnosticadas[] = $this->diagnosticarFila($seguimiento, $fila, in_array($fila['codigo_meta_producto'], $duplicados, true), $usaMatrizProyectos);
        }

        $validas = collect($diagnosticadas)->where('estado', 'valida')->count();
        $bloqueadas = count($diagnosticadas) - $validas;

        return [
            'summary' => [
                'total' => count($diagnosticadas),
                'validas' => $validas,
                'bloqueadas' => $bloqueadas,
            ],
            'filas' => $diagnosticadas,
        ];
    }

    /**
     * @return array{importadas: int, actualizadas: int, diagnostico: array<string, mixed>}
     */
    public function importar(SeguimientoCargaHistorica $carga, User $usuario): array
    {
        $seguimiento = $carga->seguimiento()->firstOrFail();

        if ($seguimiento->estaCerrado()) {
            throw ValidationException::withMessages([
                'archivo' => 'No se puede importar una carga histórica en un seguimiento cerrado.',
            ]);
        }

        $ruta = Storage::disk($carga->disk)->path($carga->path);
        $rutaProyectos = $carga->proyectos_path ? Storage::disk($carga->disk)->path($carga->proyectos_path) : null;
        $diagnostico = $this->diagnosticar(
            $seguimiento,
            $ruta,
            pathinfo($ruta, PATHINFO_EXTENSION) ?: 'xlsx',
            $rutaProyectos,
            $rutaProyectos ? (pathinfo($rutaProyectos, PATHINFO_EXTENSION) ?: 'xlsx') : null,
        );
        $validas = collect($diagnostico['filas'])->where('estado', 'valida')->values();
        $importadas = 0;
        $actualizadas = 0;

        DB::transaction(function () use ($validas, $seguimiento, $usuario, &$importadas, &$actualizadas): void {
            if (
                Schema::hasColumn('seguimientos', 'modo_captura')
                && $seguimiento->modo_captura !== Seguimiento::MODO_HISTORICO
            ) {
                $seguimiento->forceFill(['modo_captura' => Seguimiento::MODO_HISTORICO])->save();
            }

            foreach ($validas as $fila) {
                $existia = $this->importarFila($seguimiento, $fila, $usuario);

                if ($existia) {
                    $actualizadas++;
                } else {
                    $importadas++;
                }
            }
        });

        $carga->forceFill([
            'status' => 'importado',
            'filas_total' => $diagnostico['summary']['total'],
            'filas_validas' => $diagnostico['summary']['validas'],
            'filas_bloqueadas' => $diagnostico['summary']['bloqueadas'],
            'filas_importadas' => $importadas,
            'filas_actualizadas' => $actualizadas,
            'diagnostico' => $diagnostico,
            'imported_at' => now(),
        ])->save();

        return compact('importadas', 'actualizadas', 'diagnostico');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leer(string $ruta, string $extension): array
    {
        $reader = $this->reader($ruta, strtolower($extension));
        $reader->open($ruta);

        $mapa = null;
        $filas = [];
        $numero = 0;

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $row) {
                    $numero++;
                    $valores = array_map(fn (mixed $valor): mixed => $valor instanceof Cell ? $valor->getValue() : $valor, $row->toArray());

                    if ($mapa === null) {
                        $mapa = $this->mapaEncabezados($valores);

                        continue;
                    }

                    $fila = [
                        'fila' => $numero,
                        'codigo_meta_producto' => $this->limpiarCodigo($valores[$mapa['codigo_meta_producto']] ?? null),
                        'meta_producto' => $this->texto($valores[$mapa['meta_producto']] ?? null),
                        'responsable' => $this->texto($valores[$mapa['responsable']] ?? null),
                        'pct_avance_financiero_raw' => $valores[$mapa['pct_avance_financiero']] ?? null,
                        'programacion_fisica_raw' => $valores[$mapa['programacion_fisica']] ?? null,
                        'avance_fisico_raw' => $valores[$mapa['avance_fisico']] ?? null,
                        'pct_avance_fisico_raw' => $valores[$mapa['pct_avance_fisico']] ?? null,
                        'asignado_raw' => $valores[$mapa['asignado']] ?? null,
                        'comprometido_raw' => $valores[$mapa['comprometido']] ?? null,
                        'obligado_raw' => $valores[$mapa['obligado']] ?? null,
                        'observaciones' => $this->texto($valores[$mapa['observaciones']] ?? null),
                    ];

                    if ($this->filaVacia($fila)) {
                        continue;
                    }

                    $filas[] = $fila;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($mapa === null) {
            throw ValidationException::withMessages([
                'archivo' => 'No se encontró la fila de encabezados. Se esperaba "COD. META PRODUCTO", "META PRODUCTO", "RESPONSABLE" y columnas de avance.',
            ]);
        }

        return $filas;
    }

    /**
     * Lee la matriz desagregada por proyecto. En este formato el código de meta se repite
     * naturalmente por cada BPIN/fuente, por eso esas repeticiones no se consideran error.
     *
     * @return list<array<string, mixed>>
     */
    private function leerProyectos(string $ruta, string $extension): array
    {
        $reader = $this->reader($ruta, strtolower($extension));
        $reader->open($ruta);

        $mapa = null;
        $filas = [];
        $numero = 0;

        try {
            foreach ($reader->getSheetIterator() as $hoja) {
                foreach ($hoja->getRowIterator() as $row) {
                    $numero++;
                    $valores = array_map(fn (mixed $valor): mixed => $valor instanceof Cell ? $valor->getValue() : $valor, $row->toArray());

                    if ($mapa === null) {
                        $mapa = $this->mapaEncabezadosProyectos($valores);

                        continue;
                    }

                    $fila = [
                        'fila' => $numero,
                        'codigo_meta_producto' => $this->limpiarCodigo($valores[$mapa['codigo_meta_producto']] ?? null),
                        'meta_producto' => $this->texto($valores[$mapa['meta_producto']] ?? null),
                        'responsable' => $this->texto($valores[$mapa['responsable']] ?? null),
                        'bpin_archivo' => $this->limpiarCodigo($valores[$mapa['bpin']] ?? null),
                        'nombre_proyecto_archivo' => $this->texto($valores[$mapa['nombre_proyecto']] ?? null),
                        'fuente_archivo' => $this->texto($valores[$mapa['fuente_financiacion']] ?? null),
                        'pct_avance_financiero_raw' => $valores[$mapa['pct_avance_financiero']] ?? null,
                        'programacion_fisica_raw' => $valores[$mapa['programacion_fisica']] ?? null,
                        'avance_fisico_raw' => $valores[$mapa['avance_fisico']] ?? null,
                        'pct_avance_fisico_raw' => $valores[$mapa['pct_avance_fisico']] ?? null,
                        'asignado_raw' => $valores[$mapa['asignacion_fuente']] ?? null,
                        'comprometido_raw' => $valores[$mapa['compromisos_fuente']] ?? null,
                        'obligado_raw' => $valores[$mapa['obligado_fuente']] ?? null,
                        'observaciones' => $this->texto($valores[$mapa['observaciones']] ?? null),
                    ];

                    if ($this->filaVacia($fila) || $fila['bpin_archivo'] === '') {
                        continue;
                    }

                    $filas[] = $fila;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($mapa === null) {
            throw ValidationException::withMessages([
                'archivo_proyectos' => 'No se encontró la fila de encabezados del archivo de proyectos. Se esperaba "COD. META PRODUCTO", "BPIN", "NOMBRE DE PROYECTO" y columnas por fuente.',
            ]);
        }

        return $filas;
    }

    /**
     * @param  list<mixed>  $valores
     * @return array<string, int>|null
     */
    private function mapaEncabezados(array $valores): ?array
    {
        $normalizados = array_map(fn (mixed $valor): string => $this->normalizar((string) $valor), $valores);
        $aliases = [
            'codigo_meta_producto' => ['COD META PRODUCTO', 'CODIGO META PRODUCTO'],
            'meta_producto' => ['META PRODUCTO'],
            'responsable' => ['RESPONSABLE'],
            'pct_avance_financiero' => ['% AVANCE FINANCIERO', 'AVANCE FINANCIERO'],
            'programacion_fisica' => ['PROGRAMACION FISICA', 'PROGRAMACIÓN FÍSICA'],
            'avance_fisico' => ['AVANCE FISICO', 'AVANCE FÍSICO'],
            'pct_avance_fisico' => ['% AVANCE FISICO', '% AVANCE FÍSICO'],
            'asignado' => ['ASIGNADO'],
            'comprometido' => ['COMPROMETIDO'],
            'obligado' => ['OBLIGADO'],
            'observaciones' => ['OBSERVACIONES'],
        ];
        $mapa = [];

        foreach ($aliases as $campo => $nombres) {
            foreach ($nombres as $nombre) {
                $indice = array_search($this->normalizar($nombre), $normalizados, true);

                if ($indice !== false) {
                    $mapa[$campo] = $indice;

                    break;
                }
            }
        }

        foreach (array_diff(array_keys($aliases), ['observaciones']) as $obligatoria) {
            if (! array_key_exists($obligatoria, $mapa)) {
                return null;
            }
        }

        $mapa['observaciones'] ??= ($mapa['obligado'] + 1);

        return $mapa;
    }

    /**
     * @param  list<mixed>  $valores
     * @return array<string, int>|null
     */
    private function mapaEncabezadosProyectos(array $valores): ?array
    {
        $mapa = $this->mapaEncabezados($valores);

        if ($mapa === null) {
            return null;
        }

        $normalizados = array_map(fn (mixed $valor): string => $this->normalizar((string) $valor), $valores);
        $extras = [
            'bpin' => ['BPIN'],
            'nombre_proyecto' => ['NOMBRE DE PROYECTO', 'PROYECTO'],
            'fuente_financiacion' => ['FUENTE DE FINANCIACION', 'FUENTE DE FINANCIACIÓN'],
            'asignacion_fuente' => ['ASIGNACION POR FUENTE', 'ASIGNACIÓN POR FUENTE'],
            'compromisos_fuente' => ['COMPROMISOS POR FUENTE', 'COMPROMETIDO POR FUENTE'],
            'obligado_fuente' => ['OBLIGADO POR FUENTE', 'OBLIGACIONES POR FUENTE'],
        ];

        foreach ($extras as $campo => $nombres) {
            foreach ($nombres as $nombre) {
                $indice = array_search($this->normalizar($nombre), $normalizados, true);

                if ($indice !== false) {
                    $mapa[$campo] = $indice;

                    break;
                }
            }

            if (! array_key_exists($campo, $mapa)) {
                return null;
            }
        }

        return $mapa;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function diagnosticarFila(Seguimiento $seguimiento, array $fila, bool $duplicado, bool $usaMatrizProyectos = false): array
    {
        $errores = [];
        $notas = [];
        $fila['programacion_fisica'] = $this->numero($fila['programacion_fisica_raw'], 'PROGRAMACION FISICA', $errores, $notas);
        $fila['avance_fisico'] = $this->numero($fila['avance_fisico_raw'], 'AVANCE FISICO', $errores, $notas);
        $fila['pct_avance_fisico'] = $this->numero($fila['pct_avance_fisico_raw'], '% AVANCE FISICO', $errores, $notas, true);
        $fila['pct_avance_financiero'] = $this->numero($fila['pct_avance_financiero_raw'], '% AVANCE FINANCIERO', $errores, $notas, true);
        $fila['asignado'] = $this->numero($fila['asignado_raw'], 'ASIGNADO', $errores, $notas);
        $fila['comprometido'] = $this->numero($fila['comprometido_raw'], 'COMPROMETIDO', $errores, $notas);
        $fila['obligado'] = $this->numero($fila['obligado_raw'], 'OBLIGADO', $errores, $notas);

        if (! $this->codigoPareceValido($fila['codigo_meta_producto'])) {
            $errores[] = 'Código de meta producto vacío, inválido o textual.';
        }

        if ($duplicado) {
            $errores[] = 'Código de meta producto repetido en el archivo; se bloquea para revisión manual.';
        }

        $meta = null;
        $proyecto = null;
        $dependencia = null;
        $fuente = null;
        $usaFuenteGenerica = true;

        if ($this->codigoPareceValido($fila['codigo_meta_producto'])) {
            $meta = MetaProducto::query()->where('codigo', $fila['codigo_meta_producto'])->first();

            if (! $meta) {
                $errores[] = 'La meta producto no existe en SIID.';
            } else {
                $proyectos = $meta->proyectos()->withoutGlobalScopes()->get();

                if ($usaMatrizProyectos) {
                    $proyecto = Proyecto::query()->withoutGlobalScopes()->where('bpin', $fila['bpin_archivo'])->first();
                    $dependencia = $meta->dependencia ?: $this->dependenciaDesdeResponsable($fila['responsable']);

                    if (! $this->codigoPareceValido($fila['bpin_archivo'])) {
                        $errores[] = 'BPIN vacío o inválido en el archivo de proyectos.';
                    }

                    if (! $dependencia) {
                        $errores[] = 'No fue posible resolver la dependencia responsable.';
                    } elseif ($fila['responsable'] !== '' && ! $this->responsableCompatible($fila['responsable'], $dependencia)) {
                        $errores[] = 'La dependencia responsable del Excel no coincide con la dependencia SIID.';
                    }

                    [$fuente, $usaFuenteGenerica, $fuenteEtiqueta] = $this->fuenteDesdeArchivo($fila['fuente_archivo']);
                } elseif ($proyectos->isEmpty()) {
                    $errores[] = 'La meta producto no está asociada a ningún BPIN.';
                } elseif ($proyectos->count() > 1) {
                    $errores[] = 'La meta producto está asociada a más de un BPIN; requiere resolución manual.';
                } else {
                    /** @var Proyecto $proyecto */
                    $proyecto = $proyectos->first();
                    $dependencia = $meta->dependencia;

                    if (! $dependencia) {
                        $dependencia = $this->dependenciaDesdeResponsable($fila['responsable']);
                    }

                    if (! $dependencia) {
                        $errores[] = 'No fue posible resolver la dependencia responsable.';
                    } else {
                        if ($fila['responsable'] !== '' && ! $this->responsableCompatible($fila['responsable'], $dependencia)) {
                            $errores[] = 'La dependencia responsable del Excel no coincide con la dependencia SIID.';
                        }

                        if (! $proyecto->dependencias()->where('dependencias.id', $dependencia->id)->exists()) {
                            $errores[] = 'El BPIN de la meta no está asociado a la dependencia responsable.';
                        }

                        [$fuente, $usaFuenteGenerica, $errorTecho] = $this->fuentePara($seguimiento, $proyecto, $dependencia, (float) $fila['comprometido']);
                        if ($errorTecho !== null) {
                            $errores[] = $errorTecho;
                        }
                    }
                }
            }
        }

        return $fila + [
            'estado' => $errores === [] ? 'valida' : 'bloqueada',
            'errores' => $errores,
            'notas' => $notas,
            'meta_producto_id' => $meta?->id,
            'proyecto_id' => $proyecto?->id,
            'bpin' => $proyecto?->bpin ?? ($fila['bpin_archivo'] ?? null),
            'nombre_proyecto' => $proyecto?->nombre ?? ($fila['nombre_proyecto_archivo'] ?? null),
            'dependencia_id' => $dependencia?->id,
            'dependencia' => $dependencia?->etiqueta(),
            'fuente_financiacion_id' => $fuente?->id,
            'fuente' => $fuente?->etiqueta() ?? ($fuenteEtiqueta ?? self::FUENTE_GENERICA_CODIGO.' — '.self::FUENTE_GENERICA_NOMBRE),
            'usa_fuente_generica' => $usaFuenteGenerica,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function importarFila(Seguimiento $seguimiento, array $fila, User $usuario): bool
    {
        if (! empty($fila['bpin'])) {
            $proyecto = Proyecto::query()->withoutGlobalScopes()->firstOrCreate([
                'bpin' => $fila['bpin'],
            ], [
                'nombre' => $fila['nombre_proyecto'] ?: 'Proyecto '.$fila['bpin'],
                'activo' => true,
            ]);
            $proyecto->dependencias()->syncWithoutDetaching([
                $fila['dependencia_id'] => ['es_responsable_principal' => true, 'origen' => 'historico'],
            ]);
            MetaProducto::query()->findOrFail($fila['meta_producto_id'])->proyectos()->syncWithoutDetaching([$proyecto->id]);
            $fila['proyecto_id'] = $proyecto->id;
        }

        $fuente = ! empty($fila['fuente_archivo']) && ! $fila['usa_fuente_generica']
            ? $this->fuenteDesdeArchivo($fila['fuente_archivo'], persistir: true)[0]
            : ($fila['usa_fuente_generica']
            ? $this->fuenteGenerica()
            : FuenteFinanciacion::query()->findOrFail($fila['fuente_financiacion_id']));

        if ($fila['usa_fuente_generica'] || ! empty($fila['fuente_archivo'])) {
            $this->crearTechoGenerico($seguimiento, $fila, $fuente);
        }

        $reporte = ReporteProyecto::query()->withoutGlobalScopes()->firstOrNew([
            'seguimiento_id' => $seguimiento->id,
            'proyecto_id' => $fila['proyecto_id'],
            'dependencia_id' => $fila['dependencia_id'],
        ]);
        $existiaReporte = $reporte->exists;
        $reporte->fill([
            'estado' => EstadoReporteProyecto::Reportado,
            'reportado_por' => $usuario->id,
            'reportado_at' => now(),
        ])->save();

        $codigoActividad = sprintf('HIST-%d-%02d-%s', $seguimiento->vigencia, $seguimiento->mes, $fila['codigo_meta_producto']);
        $actividad = Actividad::query()->withoutGlobalScopes()
            ->where('proyecto_id', $fila['proyecto_id'])
            ->where('dependencia_id', $fila['dependencia_id'])
            ->where('meta_producto_id', $fila['meta_producto_id'])
            ->where('codigo', $codigoActividad)
            ->first();
        $existiaActividad = (bool) $actividad;
        $actividad ??= new Actividad;
        $actividadData = [
            'proyecto_id' => $fila['proyecto_id'],
            'dependencia_id' => $fila['dependencia_id'],
            'meta_producto_id' => $fila['meta_producto_id'],
            'codigo' => $codigoActividad,
            'nombre' => 'Actividad consolidada histórica '.$seguimiento->etiqueta(),
            'unidad_medida' => 'Meta producto',
            'cantidad_programada' => $fila['programacion_fisica'],
            'activo' => true,
            'created_by' => $actividad->created_by ?: $usuario->id,
        ];

        if (Schema::hasColumn('actividades', 'origen')) {
            $actividadData['origen'] = Actividad::ORIGEN_HISTORICO;
        }

        $actividad->fill($actividadData)->save();

        $programacion = ActividadProgramacion::query()->withoutGlobalScopes()->firstOrNew([
            'actividad_id' => $actividad->id,
            'fuente_financiacion_id' => $fuente->id,
            'vigencia' => $seguimiento->vigencia,
        ]);
        $existiaProgramacion = $programacion->exists;
        $programacion->fill(['valor_asignado' => $fila['asignado']])->save();

        $descripcion = trim(implode("\n\n", array_filter([
            $fila['observaciones'],
            'Carga histórica validada sin evidencia.',
        ])));

        $avance = AvanceFisico::query()->withoutGlobalScopes()->firstOrNew([
            'reporte_proyecto_id' => $reporte->id,
            'actividad_id' => $actividad->id,
        ]);
        $existiaAvance = $avance->exists;
        $avance->fill([
            'cantidad' => $fila['avance_fisico'],
            'fecha_ejecucion' => $seguimiento->fecha_corte,
            'descripcion' => $descripcion,
        ])->save();

        $ejecucion = EjecucionFinanciera::query()->withoutGlobalScopes()->firstOrNew([
            'reporte_proyecto_id' => $reporte->id,
            'actividad_id' => $actividad->id,
            'fuente_financiacion_id' => $fuente->id,
        ]);
        $existiaEjecucion = $ejecucion->exists;
        $ejecucion->fill([
            'comprometido' => $fila['comprometido'],
            'obligado' => $fila['obligado'],
            'pagado' => $fila['obligado'],
        ])->save();

        return $existiaReporte || $existiaActividad || $existiaProgramacion || $existiaAvance || $existiaEjecucion;
    }

    /**
     * @return array{0: FuenteFinanciacion|null, 1: bool, 2: string|null}
     */
    private function fuentePara(Seguimiento $seguimiento, Proyecto $proyecto, Dependencia $dependencia, float $comprometido): array
    {
        $techos = Techo::query()->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->where('proyecto_id', $proyecto->id)
            ->where('dependencia_id', $dependencia->id)
            ->with('fuente')
            ->get();

        if ($techos->pluck('fuente_financiacion_id')->unique()->count() !== 1) {
            return [null, true, null];
        }

        /** @var Techo $techo */
        $techo = $techos->first();

        if ($comprometido > ((float) $techo->valor + 0.01)) {
            return [$techo->fuente, false, 'El comprometido supera el techo disponible de la fuente única.'];
        }

        return [$techo->fuente, false, null];
    }

    private function crearTechoGenerico(Seguimiento $seguimiento, array $fila, FuenteFinanciacion $fuente): void
    {
        $valor = max((float) $fila['asignado'], (float) $fila['comprometido'], (float) $fila['obligado']);

        Techo::query()->withoutGlobalScopes()->updateOrCreate([
            'seguimiento_id' => $seguimiento->id,
            'proyecto_id' => $fila['proyecto_id'],
            'fuente_financiacion_id' => $fuente->id,
            'dependencia_id' => $fila['dependencia_id'],
        ], [
            'valor_pasiva' => $valor,
            'valor_ajuste' => null,
            'base' => 'historico',
            'pasiva_carga_id' => null,
            'lineas_count' => 0,
        ]);
    }

    private function fuenteGenerica(): FuenteFinanciacion
    {
        return FuenteFinanciacion::query()->firstOrCreate([
            'codigo' => self::FUENTE_GENERICA_CODIGO,
        ], [
            'nombre' => self::FUENTE_GENERICA_NOMBRE,
            'tipo' => 'Histórico',
            'grupo_reporte' => GrupoFuenteFinanciacion::Otros,
            'activo' => true,
        ]);
    }

    /**
     * @return array{0: FuenteFinanciacion|null, 1: bool, 2: string}
     */
    private function fuenteDesdeArchivo(?string $valor, bool $persistir = false): array
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return [null, true, self::FUENTE_GENERICA_CODIGO.' — '.self::FUENTE_GENERICA_NOMBRE];
        }

        [$codigo, $nombre] = $this->codigoYNombreFuente($valor);

        if ($codigo === '') {
            return [null, true, self::FUENTE_GENERICA_CODIGO.' — '.self::FUENTE_GENERICA_NOMBRE];
        }

        $fuente = FuenteFinanciacion::query()->where('codigo', $codigo)->first();

        if (! $fuente && $persistir) {
            $fuente = FuenteFinanciacion::query()->create([
                'codigo' => $codigo,
                'nombre' => $nombre !== '' ? $nombre : $valor,
                'tipo' => 'Histórico',
                'grupo_reporte' => GrupoFuenteFinanciacion::sugerir($codigo, 'Histórico', $nombre),
                'activo' => true,
            ]);
        }

        return [$fuente, false, $codigo.' — '.($fuente?->nombre ?? ($nombre !== '' ? $nombre : $valor))];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function codigoYNombreFuente(string $valor): array
    {
        $partes = preg_split('/\s*[-–—]\s*/u', $valor, 2);

        if (is_array($partes) && count($partes) === 2) {
            return [Str::limit(trim($partes[0]), 20, ''), trim($partes[1]) ?: $valor];
        }

        if (preg_match('/^(\d+\s+[A-ZÁÉÍÓÚÑ]{1,6})\s+(.+)$/u', $valor, $matches) === 1) {
            return [Str::limit(trim($matches[1]), 20, ''), trim($matches[2])];
        }

        if (preg_match('/^([A-Z0-9]{1,20})\s+(.+)$/u', Str::ascii($valor), $matches) === 1) {
            return [trim($matches[1]), trim(mb_substr($valor, mb_strlen($matches[1]))) ?: $valor];
        }

        $codigo = (string) Str::of(Str::ascii($valor))
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_');

        if (mb_strlen($codigo) > 20) {
            $codigo = mb_substr($codigo, 0, 15).'_'.mb_substr(hash('crc32b', $valor), 0, 4);
        }

        return [$codigo !== '' ? $codigo : self::FUENTE_GENERICA_CODIGO, $valor];
    }

    private function dependenciaDesdeResponsable(string $responsable): ?Dependencia
    {
        $normalizado = $this->normalizar($responsable);

        if ($normalizado === '') {
            return null;
        }

        return Dependencia::query()
            ->get()
            ->first(fn (Dependencia $dependencia): bool => $this->responsableCompatible($responsable, $dependencia));
    }

    private function responsableCompatible(string $responsable, Dependencia $dependencia): bool
    {
        $responsable = $this->normalizar($responsable);
        $opciones = array_filter([
            $this->normalizar((string) $dependencia->codigo),
            $this->normalizar((string) $dependencia->sigla),
            $this->normalizar((string) $dependencia->nombre),
        ]);

        foreach ($opciones as $opcion) {
            if ($responsable === $opcion) {
                return true;
            }

            if (mb_strlen($responsable) >= 5 && str_contains($opcion, $responsable)) {
                return true;
            }

            if (mb_strlen($opcion) >= 5 && str_contains($responsable, $opcion)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $errores
     * @param  list<string>  $notas
     */
    private function numero(mixed $valor, string $campo, array &$errores, array &$notas, bool $admiteNoProgram = false): float
    {
        if (is_string($valor) && $this->normalizar($valor) === 'NO PROGRAM') {
            $notas[] = "{$campo}: NO PROGRAM se interpretó como cero.";

            return 0.0;
        }

        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return $this->numeroEnRango((float) $valor, $campo, $errores);
        }

        $texto = trim((string) $valor);

        if (str_starts_with($texto, '=')) {
            $notas[] = "{$campo}: fórmula de Excel no evaluada; se interpretó como cero.";

            return 0.0;
        }

        if (is_numeric($texto)) {
            return $this->numeroEnRango((float) $texto, $campo, $errores);
        }

        $limpio = preg_replace('/[^\d,.\-%]/', '', $texto) ?? '';

        if ($limpio === '' || $limpio === '-' || $limpio === '%') {
            if (! $admiteNoProgram) {
                $errores[] = "{$campo}: valor no numérico.";
            }

            return 0.0;
        }

        return $this->numeroEnRango(LectorPasiva::numero(str_replace('%', '', $limpio)), $campo, $errores);
    }

    /**
     * @param  list<string>  $errores
     */
    private function numeroEnRango(float $valor, string $campo, array &$errores): float
    {
        if (! is_finite($valor) || abs($valor) > self::MAX_DECIMAL_20_2) {
            $errores[] = "{$campo}: valor fuera del rango permitido.";

            return 0.0;
        }

        return round($valor, 4);
    }

    private function limpiarCodigo(mixed $valor): string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }

        if (is_float($valor) && floor($valor) === $valor) {
            return (string) (int) $valor;
        }

        $texto = trim((string) $valor);

        if (preg_match('/^\d+(\.0+)?$/', $texto) === 1) {
            return (string) (int) $texto;
        }

        return $texto;
    }

    private function codigoPareceValido(string $codigo): bool
    {
        return preg_match('/^\d{8,}$/', $codigo) === 1;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        foreach (['codigo_meta_producto', 'meta_producto', 'responsable', 'asignado_raw', 'comprometido_raw', 'obligado_raw'] as $campo) {
            if (trim((string) ($fila[$campo] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function texto(mixed $valor): string
    {
        return trim((string) $valor);
    }

    private function normalizar(string $texto): string
    {
        return (string) Str::of(Str::ascii(str_replace('%', ' PORCENTAJE ', $texto)))
            ->upper()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();
    }

    private function reader(string $ruta, string $extension): ReaderInterface
    {
        if ($extension === 'xlsx') {
            return new XlsxReader;
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            return new CsvReader($this->opcionesCsv($this->delimitador($ruta)));
        }

        throw ValidationException::withMessages([
            'archivo' => 'Formato no admitido. Cargue el avance histórico como .xlsx o .csv.',
        ]);
    }

    private function opcionesCsv(string $delimitador): CsvOptions
    {
        if ((new \ReflectionClass(CsvOptions::class))->getConstructor() !== null) {
            return new CsvOptions(FIELD_DELIMITER: $delimitador);
        }

        $opciones = new CsvOptions;
        $opciones->FIELD_DELIMITER = $delimitador;

        return $opciones;
    }

    private function delimitador(string $ruta): string
    {
        $muestra = (string) file_get_contents($ruta, false, null, 0, 8192);
        $conteos = [';' => substr_count($muestra, ';'), ',' => substr_count($muestra, ','), "\t" => substr_count($muestra, "\t")];
        arsort($conteos);

        return (string) array_key_first($conteos);
    }
}
