<?php

namespace App\Services\Intelligence\ReporteSectorial;

use App\Enums\EstadoReporteProyecto;
use App\Enums\EstadoSeguimiento;
use App\Exceptions\CorteCerradoException;
use App\Models\Actividad;
use App\Models\ActividadProgramacion;
use App\Models\AvanceFisico;
use App\Models\Dependencia;
use App\Models\EjecucionFinanciera;
use App\Models\Evidencia;
use App\Models\Focalizacion;
use App\Models\FuenteFinanciacion;
use App\Models\Proyecto;
use App\Models\ReporteProyecto;
use App\Models\ReporteProyectoDistribucion;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del reporte mensual sectorial. Todo cambio corre en una transacción.
 */
class ServicioReporteSectorial
{
    public function __construct(private readonly AuditLogger $auditoria) {}

    public static function pesos(float|string|null $valor): string
    {
        return '$'.number_format((float) $valor, 0, ',', '.');
    }

    /**
     * Dependencias visibles para el usuario que tienen techo en este proyecto y seguimiento.
     *
     * @return Collection<int, Dependencia>
     */
    public function dependenciasConTecho(Seguimiento $seguimiento, Proyecto $proyecto): Collection
    {
        $ids = Techo::query()->delSeguimiento($seguimiento)->where('proyecto_id', $proyecto->id)->distinct()->pluck('dependencia_id');

        return Dependencia::query()->whereIn('id', $ids)->orderBy('nombre')->get();
    }

    /**
     * Reporte de la dependencia sobre el proyecto en el seguimiento. Se crea al primer ingreso si el corte está abierto.
     */
    public function reporte(Seguimiento $seguimiento, Proyecto $proyecto, Dependencia $dependencia): ReporteProyecto
    {
        $atributos = ['seguimiento_id' => $seguimiento->id, 'proyecto_id' => $proyecto->id, 'dependencia_id' => $dependencia->id];
        $reporte = ReporteProyecto::query()->where($atributos)->first();

        if ($reporte === null) {
            $reporte = $seguimiento->estaCerrado()
                ? new ReporteProyecto($atributos + ['estado' => EstadoReporteProyecto::Borrador])
                : ReporteProyecto::query()->create($atributos);
        }

        return $reporte->setRelation('seguimiento', $seguimiento)->setRelation('proyecto', $proyecto)->setRelation('dependencia', $dependencia);
    }

    /**
     * @param  array{codigo?: string|null, nombre: string, unidad_medida?: string|null, cantidad_programada?: float|string|null, meta_producto_id?: int|null}  $datos
     * @param  list<array{fuente_financiacion_id: int, valor_asignado: float|string}>  $programacion
     */
    public function crearActividad(Seguimiento $seguimiento, Proyecto $proyecto, Dependencia $dependencia, array $datos, array $programacion, User $usuario): Actividad
    {
        return DB::transaction(function () use ($seguimiento, $proyecto, $dependencia, $datos, $programacion, $usuario): Actividad {
            $attributes = $datos + [
                'proyecto_id' => $proyecto->id,
                'dependencia_id' => $dependencia->id,
                'created_by' => $usuario->id,
                'activo' => true,
            ];

            if (Schema::hasColumn('actividades', 'origen')) {
                $attributes['origen'] = Actividad::ORIGEN_CAPTURA;
            }

            $actividad = Actividad::query()->create($attributes);

            foreach ($programacion as $fila) {
                if ((float) $fila['valor_asignado'] <= 0) {
                    continue;
                }

                ActividadProgramacion::query()->create([
                    'actividad_id' => $actividad->id,
                    'fuente_financiacion_id' => $fila['fuente_financiacion_id'],
                    'vigencia' => $seguimiento->vigencia,
                    'valor_asignado' => $fila['valor_asignado'],
                ]);
            }

            $this->validarProgramacionContraTecho($seguimiento, $proyecto, $dependencia);

            return $actividad;
        });
    }

    /**
     * Ejecución financiera acumulada por actividad y fuente. Lo comprometido por fuente no puede superar el techo
     * derivado de la pasiva; obligado ≤ comprometido y pagado ≤ obligado.
     *
     * @param  list<array{actividad_id: int, fuente_financiacion_id: int, programado?: float|string|null, comprometido: float|string, obligado: float|string, pagado: float|string}>  $filas
     */
    public function guardarEjecucion(ReporteProyecto $reporte, array $filas): void
    {
        $this->asegurarEditable($reporte);

        DB::transaction(function () use ($reporte, $filas): void {
            $techos = $this->techosDelReporte($reporte, bloquear: true);
            $actividades = $this->actividadesDelReporte($reporte)->keyBy('id');
            $errores = [];
            $nuevas = [];
            $programaciones = [];

            foreach ($filas as $indice => $fila) {
                $actividad = $actividades->get((int) $fila['actividad_id']);
                $comprometido = round((float) $fila['comprometido'], 2);
                $obligado = round((float) $fila['obligado'], 2);
                $pagado = round((float) $fila['pagado'], 2);

                if ($actividad === null) {
                    $errores["ejecucion.{$indice}"] = 'La actividad no pertenece a este proyecto y dependencia.';

                    continue;
                }

                $fuenteId = (int) $fila['fuente_financiacion_id'];

                if (! $techos->has($fuenteId)) {
                    $errores["ejecucion.{$indice}.fuente_financiacion_id"] = $actividad->etiqueta().': la fuente no pertenece a los techos de este proyecto.';

                    continue;
                }

                if ($obligado > $comprometido || $pagado > $obligado) {
                    $errores["ejecucion.{$indice}"] = $actividad->etiqueta().': debe cumplirse comprometido ≥ obligado ≥ pagado.';

                    continue;
                }

                $asignado = array_key_exists('programado', $fila) && $fila['programado'] !== null
                    ? round((float) $fila['programado'], 2)
                    : ActividadProgramacion::sinFiltroSectorial()
                        ->where('actividad_id', $actividad->id)
                        ->where('fuente_financiacion_id', $fuenteId)
                        ->where('vigencia', $reporte->seguimiento->vigencia)
                        ->value('valor_asignado');

                if ($asignado !== null && ($comprometido > $asignado || $obligado > $asignado || $pagado > $asignado)) {
                    $errores["ejecucion.{$indice}"] = sprintf('%s: comprometido, obligado y pagado no pueden superar el asignado de esta meta y fuente (%s).', $actividad->etiqueta(), self::pesos((float) $asignado));

                    continue;
                }

                if (array_key_exists('programado', $fila) && $fila['programado'] !== null) {
                    $programaciones[$actividad->id.'-'.$fuenteId] = [
                        'actividad_id' => $actividad->id,
                        'fuente_financiacion_id' => $fuenteId,
                        'valor_asignado' => round((float) $fila['programado'], 2),
                    ];
                }

                $nuevas[$actividad->id.'-'.$fuenteId] = [
                    'actividad_id' => $actividad->id,
                    'fuente_financiacion_id' => $fuenteId,
                    'comprometido' => $comprometido,
                    'obligado' => $obligado,
                    'pagado' => $pagado,
                ];
            }

            if ($errores !== []) {
                throw ValidationException::withMessages($errores);
            }

            foreach ($programaciones as $programacion) {
                ActividadProgramacion::query()->updateOrCreate(
                    [
                        'actividad_id' => $programacion['actividad_id'],
                        'fuente_financiacion_id' => $programacion['fuente_financiacion_id'],
                        'vigencia' => $reporte->seguimiento->vigencia,
                    ],
                    ['valor_asignado' => $programacion['valor_asignado']],
                );
            }

            if ($programaciones !== []) {
                $this->validarProgramacionContraTecho($reporte->seguimiento, $reporte->proyecto, $reporte->dependencia);
            }

            $existentes = $reporte->exists
                ? $reporte->ejecuciones()->get()->mapWithKeys(fn (EjecucionFinanciera $ejecucion): array => [
                    $ejecucion->actividad_id.'-'.$ejecucion->fuente_financiacion_id => [
                        'fuente_financiacion_id' => $ejecucion->fuente_financiacion_id,
                        'comprometido' => (float) $ejecucion->comprometido,
                        'obligado' => (float) $ejecucion->obligado,
                        'pagado' => (float) $ejecucion->pagado,
                    ],
                ])->all()
                : [];

            $this->validarContraTecho($reporte, array_replace($existentes, $nuevas), $techos, $nuevas);

            foreach ($nuevas as $fila) {
                EjecucionFinanciera::query()->updateOrCreate(
                    ['reporte_proyecto_id' => $reporte->id, 'actividad_id' => $fila['actividad_id'], 'fuente_financiacion_id' => $fila['fuente_financiacion_id']],
                    ['comprometido' => $fila['comprometido'], 'obligado' => $fila['obligado'], 'pagado' => $fila['pagado']],
                );
            }
        });
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    public function guardarAvance(ReporteProyecto $reporte, Actividad $actividad, float $cantidad, ?string $fechaEjecucion, ?string $descripcion, array $archivos, ?string $descripcionEvidencia, ?string $evidenciaUrl, User $usuario): AvanceFisico
    {
        $this->asegurarEditable($reporte);

        if ($actividad->proyecto_id !== $reporte->proyecto_id || $actividad->dependencia_id !== $reporte->dependencia_id) {
            throw ValidationException::withMessages(['actividad' => 'La actividad no pertenece a este proyecto y dependencia.']);
        }

        return DB::transaction(function () use ($reporte, $actividad, $cantidad, $fechaEjecucion, $descripcion, $archivos, $descripcionEvidencia, $evidenciaUrl, $usuario): AvanceFisico {
            $avance = AvanceFisico::query()->updateOrCreate(
                ['reporte_proyecto_id' => $reporte->id, 'actividad_id' => $actividad->id],
                ['reportado_fisico' => true, 'cantidad' => $cantidad, 'fecha_ejecucion' => $fechaEjecucion, 'descripcion' => $descripcion],
            );

            foreach ($archivos as $archivo) {
                $this->adjuntarEvidencia($avance, $archivo, $descripcionEvidencia, $usuario);
            }
            if ($evidenciaUrl !== null && trim($evidenciaUrl) !== '') {
                $this->adjuntarEnlaceEvidencia($avance, trim($evidenciaUrl), $descripcionEvidencia, $usuario);
            }

            return $avance;
        });
    }

    public function guardarObservacion(ReporteProyecto $reporte, Actividad $actividad, string $observacion): AvanceFisico
    {
        $this->asegurarEditable($reporte);
        if ($actividad->proyecto_id !== $reporte->proyecto_id || $actividad->dependencia_id !== $reporte->dependencia_id) {
            throw ValidationException::withMessages(['actividad' => 'La actividad no pertenece a este proyecto y dependencia.']);
        }

        return AvanceFisico::query()->updateOrCreate(
            ['reporte_proyecto_id' => $reporte->id, 'actividad_id' => $actividad->id],
            ['descripcion' => trim($observacion)],
        );
    }

    public function adjuntarEnlaceEvidencia(AvanceFisico $avance, string $url, ?string $descripcion, User $usuario): Evidencia
    {
        $this->asegurarEditable($avance->reporte);

        return Evidencia::query()->create([
            'avance_fisico_id' => $avance->id,
            'disk' => 'url',
            'path' => $url,
            'nombre_original' => 'Carpeta de evidencia en Drive',
            'mime' => 'text/uri-list',
            'bytes' => 0,
            'sha256' => hash('sha256', $url),
            'descripcion' => $descripcion,
            'uploaded_by' => $usuario->id,
        ]);
    }

    public function adjuntarEvidencia(AvanceFisico $avance, UploadedFile $archivo, ?string $descripcion, User $usuario): Evidencia
    {
        $reporte = $avance->reporte;
        $this->asegurarEditable($reporte);

        $disk = (string) config('reporte_sectorial.disk');
        $sha256 = hash_file('sha256', (string) $archivo->getRealPath());
        $path = $archivo->store('reporte-sectorial/evidencias/'.$reporte->seguimiento_id.'/'.$reporte->id, $disk);

        return Evidencia::query()->create([
            'avance_fisico_id' => $avance->id,
            'disk' => $disk,
            'path' => $path,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime' => $archivo->getClientMimeType(),
            'bytes' => (int) $archivo->getSize(),
            'sha256' => $sha256,
            'descripcion' => $descripcion,
            'uploaded_by' => $usuario->id,
        ]);
    }

    public function eliminarEvidencia(Evidencia $evidencia): void
    {
        $this->asegurarEditable($evidencia->avanceFisico->reporte);

        DB::transaction(function () use ($evidencia): void {
            $evidencia->delete();
            if ($evidencia->disk !== 'url') {
                Storage::disk($evidencia->disk)->delete($evidencia->path);
            }
        });
    }

    /**
     * Distribución del corte anterior para precargar el formulario.
     *
     * @return array<int, array{porcentaje: float, valor: float|null, cantidad: float|null}>
     */
    public function focalizacionPrevia(ReporteProyecto $reporte): array
    {
        $anterior = $reporte->seguimiento->anterior();

        if ($anterior === null) {
            return [];
        }

        $reporteAnterior = ReporteProyecto::query()
            ->where('seguimiento_id', $anterior->id)
            ->where('proyecto_id', $reporte->proyecto_id)
            ->where('dependencia_id', $reporte->dependencia_id)
            ->first();

        if ($reporteAnterior === null) {
            return [];
        }

        return $reporteAnterior->focalizaciones()->get()->mapWithKeys(fn (Focalizacion $focalizacion): array => [
            $focalizacion->municipio_id => [
                'porcentaje' => (float) $focalizacion->porcentaje,
                'valor' => $focalizacion->valor === null ? null : (float) $focalizacion->valor,
                'cantidad' => $focalizacion->cantidad === null ? null : (float) $focalizacion->cantidad,
            ],
        ])->all();
    }

    /**
     * @param  list<array{municipio_id: int, porcentaje: float|string, valor?: float|string|null, cantidad?: float|string|null}>  $filas
     */
    public function guardarFocalizacion(ReporteProyecto $reporte, array $filas, ?string $justificacion): void
    {
        $this->asegurarEditable($reporte);

        if (! $reporte->seguimiento->usaFocalizacion() || ! $reporte->proyecto->requiereFocalizacionMensual()) {
            throw ValidationException::withMessages(['focalizacion' => 'Este corte no tiene regionalización habilitada para este proyecto.']);
        }

        $filas = array_values(array_filter($filas, fn (array $fila): bool => (float) ($fila['porcentaje'] ?? 0) > 0));
        $this->validarFocalizacion($reporte, $filas);

        $previa = $this->focalizacionPrevia($reporte);
        $justificacion = trim((string) $justificacion);

        if ($previa !== [] && $this->cambioFocalizacion($previa, $filas) && $justificacion === '') {
            throw ValidationException::withMessages(['justificacion_focalizacion' => 'La distribución por municipio cambió frente al corte anterior: escriba la justificación del cambio.']);
        }

        DB::transaction(function () use ($reporte, $filas, $justificacion): void {
            $reporte->focalizaciones()->get()->each->delete();

            foreach ($filas as $fila) {
                Focalizacion::query()->create([
                    'reporte_proyecto_id' => $reporte->id,
                    'municipio_id' => (int) $fila['municipio_id'],
                    'porcentaje' => round((float) $fila['porcentaje'], 4),
                    'valor' => isset($fila['valor']) && $fila['valor'] !== '' ? round((float) $fila['valor'], 2) : null,
                    'cantidad' => isset($fila['cantidad']) && $fila['cantidad'] !== '' ? round((float) $fila['cantidad'], 4) : null,
                ]);
            }

            $reporte->update(['justificacion_focalizacion' => $justificacion !== '' ? $justificacion : null]);
        });
    }

    /**
     * Guarda el reparto financiero acumulado del corte entre los municipios configurados.
     * La suma por fuente (y por meta cuando aplica) debe coincidir con la ejecución del reporte.
     *
     * @param  list<array{actividad_id?: int|string|null, fuente_financiacion_id: int|string, municipio_id: int|string, programado?: float|string|null, comprometido?: float|string|null, obligado?: float|string|null, pagado?: float|string|null}>  $filas
     */
    public function guardarDistribucion(ReporteProyecto $reporte, array $filas): void
    {
        $this->asegurarEditable($reporte);

        if (! $reporte->seguimiento->usaDistribucionFinanciera() || ! $reporte->proyecto->requiereFocalizacionMensual()) {
            throw ValidationException::withMessages(['distribucion' => 'Este corte no tiene regionalización financiera habilitada para este proyecto.']);
        }

        $permitidos = $reporte->proyecto->municipiosParaRegionalizacion()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $actividades = $this->actividadesParaDistribucion($reporte)->keyBy('id');
        $techos = $this->techosDelReporte($reporte);
        $meta = $this->regionalizaPorMeta($reporte);
        $normalizadas = [];
        $errores = [];

        foreach ($filas as $indice => $fila) {
            $actividadId = isset($fila['actividad_id']) && $fila['actividad_id'] !== '' ? (int) $fila['actividad_id'] : null;
            $fuenteId = isset($fila['fuente_financiacion_id']) && $fila['fuente_financiacion_id'] !== '' ? (int) $fila['fuente_financiacion_id'] : null;
            $municipioId = (int) $fila['municipio_id'];

            if ($meta && ($actividadId === null || ! $actividades->has($actividadId))) {
                $errores["distribucion.{$indice}.actividad_id"] = 'Seleccione una meta producto válida para esta distribución.';
            }
            if (! $meta && $actividadId !== null) {
                $errores["distribucion.{$indice}.actividad_id"] = 'Este corte distribuye el proyecto completo; no requiere meta producto.';
            }
            if ($permitidos !== [] && ! in_array($municipioId, $permitidos, true)) {
                $errores["distribucion.{$indice}.municipio_id"] = 'El municipio no está configurado en el proyecto.';
            }
            $clave = ($actividadId ?? 'proyecto').'-'.$municipioId;
            if (isset($normalizadas[$clave])) {
                $errores["distribucion.{$indice}"] = 'Hay filas repetidas para la misma meta, fuente y municipio.';
                continue;
            }

            $normalizadas[$clave] = [
                'actividad_id' => $actividadId,
                'fuente_financiacion_id' => null,
                'municipio_id' => $municipioId,
                'programado' => round((float) ($fila['programado'] ?? 0), 2),
                'comprometido' => round((float) ($fila['comprometido'] ?? 0), 2),
                'obligado' => 0,
                'pagado' => 0,
            ];
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $esperadosFuente = $this->totalesFinancierosDelReporte($reporte, $meta);
        $esperados = [];
        foreach ($esperadosFuente as $claveFuente => $valores) {
            $claveMeta = $meta ? explode('-', $claveFuente)[0] : 'proyecto';
            $esperados[$claveMeta] ??= ['programado' => 0.0, 'comprometido' => 0.0, 'obligado' => 0.0, 'pagado' => 0.0];
            foreach (['programado', 'comprometido', 'obligado', 'pagado'] as $campo) {
                $esperados[$claveMeta][$campo] += (float) $valores[$campo];
            }
        }
        $reportados = collect($normalizadas)->groupBy(fn (array $fila): string => (string) ($fila['actividad_id'] ?? 'proyecto'));
        $tolerancia = (float) config('reporte_sectorial.tolerancia_techo', 0.01);
        $errores = [];

        // El comprometido regionalizado nunca puede superar ni el reportado del corte ni el techo de la pasiva.
        foreach ($esperados as $clave => $esperado) {
            $total = $reportados->get($clave, collect())->reduce(function (array $totales, array $fila): array {
                foreach (['programado', 'comprometido', 'obligado', 'pagado'] as $campo) {
                    $totales[$campo] += (float) $fila[$campo];
                }
                return $totales;
            }, ['programado' => 0.0, 'comprometido' => 0.0, 'obligado' => 0.0, 'pagado' => 0.0]);

            foreach (['comprometido'] as $campo) {
                // Se permite guardar una distribución parcial para continuar
                // diligenciándola después, pero nunca superar lo reportado.
                if ($total[$campo] - $esperado[$campo] > $tolerancia) {
                    $errores['distribucion.'.$clave.'.'.$campo] = sprintf('%s no puede superar %s; se distribuyeron %s.', ucfirst($campo), self::pesos($esperado[$campo]), self::pesos($total[$campo]));
                }
            }
        }

        foreach ($reportados as $clave => $filasReportadas) {
            if (! array_key_exists($clave, $esperados) && $filasReportadas->sum(fn (array $fila): float => (float) $fila['comprometido']) > $tolerancia) {
                $errores['distribucion.'.$clave] = 'La fila no corresponde a una meta y fuente reportadas en este corte.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        DB::transaction(function () use ($reporte, $normalizadas): void {
            $reporte->distribuciones()->delete();
            foreach ($normalizadas as $fila) {
                ReporteProyectoDistribucion::query()->create(['reporte_proyecto_id' => $reporte->id] + $fila);
            }
        });
    }

    /**
     * @return array{evidencias: int, actividades_sin_evidencia: list<string>, actividades_sin_reporte_fisico: list<string>, focalizacion: bool, mensajes: list<string>}
     */
    public function pendientes(ReporteProyecto $reporte): array
    {
        $mensajes = [];
        $actividades = $this->actividadesDelReporte($reporte);
        $actividadesReportadas = $reporte->exists
            ? $reporte->avances()->pluck('actividad_id')->all()
            : [];
        $sinReporteFisico = $actividades
            ->reject(fn (Actividad $actividad): bool => in_array($actividad->id, $actividadesReportadas, true))
            ->map(fn (Actividad $actividad): string => $actividad->etiqueta())
            ->values()
            ->all();
        $sinEvidencia = $reporte->exists
            ? $reporte->avances()
                ->pendientesEvidencia()
                ->when(
                    Schema::hasColumn('actividades', 'origen'),
                    fn ($query) => $query->whereHas('actividad', fn ($actividad) => $actividad->where('origen', '!=', Actividad::ORIGEN_HISTORICO)),
                )
                ->with('actividad')
                ->get()
                ->map(fn (AvanceFisico $avance): string => $avance->actividad->etiqueta())
                ->all()
            : [];

        foreach ($sinReporteFisico as $etiqueta) {
            $mensajes[] = "Falta reportar la meta física de {$etiqueta}.";
        }

        foreach ($sinEvidencia as $etiqueta) {
            $mensajes[] = "Falta al menos una evidencia del avance físico de {$etiqueta}.";
        }

        $focalizacionPendiente = false;
        $distribucionPendiente = false;

        if ($reporte->proyecto->requiereFocalizacionMensual()) {
            if ($reporte->seguimiento->usaFocalizacion()) try {
                $filas = $reporte->exists ? $reporte->focalizaciones()->get()->map(fn (Focalizacion $f): array => $f->only(['municipio_id', 'porcentaje', 'valor', 'cantidad']))->all() : [];

                if ($filas === []) {
                    throw ValidationException::withMessages(['focalizacion' => 'Falta la focalización mensual por municipio (proyecto '.$reporte->proyecto->tipo_focalizacion->label().').']);
                }

                $this->validarFocalizacion($reporte, $filas);
            } catch (ValidationException $error) {
                $focalizacionPendiente = true;
                array_push($mensajes, ...collect($error->errors())->flatten()->all());
            }

            if ($reporte->seguimiento->usaDistribucionFinanciera()) try {
                $filas = $reporte->exists ? $reporte->distribuciones()->get()->map(fn (ReporteProyectoDistribucion $fila): array => $fila->toArray())->all() : [];
                if ($filas === []) {
                    throw ValidationException::withMessages(['distribucion' => 'Falta distribuir la ejecución financiera por municipio.']);
                }
                $this->validarDistribucionGuardada($reporte, $filas);
            } catch (ValidationException $error) {
                $distribucionPendiente = true;
                array_push($mensajes, ...collect($error->errors())->flatten()->all());
            }
        }

        try {
            $this->validarConciliacionFinanciera($reporte, $this->techosDelReporte($reporte));
        } catch (ValidationException $error) {
            array_push($mensajes, ...collect($error->errors())->flatten()->all());
        }

        return [
            'evidencias' => count($sinEvidencia),
            'actividades_sin_evidencia' => $sinEvidencia,
            'actividades_sin_reporte_fisico' => $sinReporteFisico,
            'focalizacion' => $focalizacionPendiente,
            'distribucion' => $distribucionPendiente,
            'mensajes' => $mensajes,
        ];
    }

    public function enviar(ReporteProyecto $reporte, User $usuario): void
    {
        $this->asegurarEditable($reporte);

        DB::transaction(function () use ($reporte, $usuario): void {
            $pendientes = $this->pendientes($reporte);
            $techos = $this->techosDelReporte($reporte, bloquear: true);
            $ejecuciones = $reporte->ejecuciones()->get()->map(fn (EjecucionFinanciera $e): array => [
                'fuente_financiacion_id' => $e->fuente_financiacion_id,
                'comprometido' => (float) $e->comprometido,
                'obligado' => (float) $e->obligado,
                'pagado' => (float) $e->pagado,
            ])->all();

            try {
                $this->validarContraTecho($reporte, $ejecuciones, $techos, []);
            } catch (ValidationException $error) {
                array_push($pendientes['mensajes'], ...collect($error->errors())->flatten()->all());
            }

            if ($pendientes['mensajes'] !== []) {
                throw ValidationException::withMessages(['envio' => $pendientes['mensajes']]);
            }

            $reporte->update([
                'estado' => EstadoReporteProyecto::Reportado,
                'reportado_por' => $usuario->id,
                'reportado_at' => now(),
            ]);

            $this->auditoria->log(null, 'reporte_sectorial.reportado', $usuario, ['reporte_proyecto_id' => $reporte->id]);
        });
    }

    public function aprobar(ReporteProyecto $reporte, User $usuario, ?string $observacion): void
    {
        $this->revisar($reporte, $usuario, EstadoReporteProyecto::Aprobado, $observacion);
    }

    public function devolver(ReporteProyecto $reporte, User $usuario, string $observacion): void
    {
        $this->revisar($reporte, $usuario, EstadoReporteProyecto::Devuelto, $observacion);
    }

    public function cerrar(Seguimiento $seguimiento, User $usuario): void
    {
        if ($seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        $enRevision = ReporteProyecto::sinFiltroSectorial()->delSeguimiento($seguimiento)->enEstado(EstadoReporteProyecto::Reportado)->count();

        if ($enRevision > 0) {
            throw ValidationException::withMessages(['seguimiento' => "Hay {$enRevision} reporte(s) en estado Reportado: apruébelos o devuélvalos antes de cerrar el corte."]);
        }

        DB::transaction(function () use ($seguimiento, $usuario): void {
            $seguimiento->update([
                'estado' => EstadoSeguimiento::Cerrado,
                'cerrado_por' => $usuario->id,
                'cerrado_at' => now(),
            ]);

            $this->auditoria->log(null, 'reporte_sectorial.seguimiento_cerrado', $usuario, ['seguimiento_id' => $seguimiento->id]);
        });
    }

    private function revisar(ReporteProyecto $reporte, User $usuario, EstadoReporteProyecto $estado, ?string $observacion): void
    {
        if ($reporte->seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        if ($reporte->estado !== EstadoReporteProyecto::Reportado) {
            throw ValidationException::withMessages(['estado' => 'Solo se pueden aprobar o devolver reportes en estado Reportado.']);
        }

        DB::transaction(function () use ($reporte, $usuario, $estado, $observacion): void {
            $anterior = $reporte->estado->value;
            $reporte->update([
                'estado' => $estado,
                'revisado_por' => $usuario->id,
                'revisado_at' => now(),
                'observacion_revision' => $observacion,
            ]);

            $this->auditoria->log(null, 'reporte_sectorial.'.$estado->value, $usuario, ['reporte_proyecto_id' => $reporte->id, 'observacion' => $observacion], null, ['estado' => $anterior], ['estado' => $estado->value]);
        });
    }

    private function asegurarEditable(ReporteProyecto $reporte): void
    {
        if ($reporte->seguimiento->estaCerrado()) {
            throw new CorteCerradoException;
        }

        if (! $reporte->estado->esEditablePorSector()) {
            throw ValidationException::withMessages(['estado' => 'El reporte está '.mb_strtolower($reporte->estado->label()).' y no se puede modificar.']);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Techo>
     */
    private function techosDelReporte(ReporteProyecto $reporte, bool $bloquear = false): \Illuminate\Support\Collection
    {
        return Techo::sinFiltroSectorial()
            ->where('seguimiento_id', $reporte->seguimiento_id)
            ->where('proyecto_id', $reporte->proyecto_id)
            ->where('dependencia_id', $reporte->dependencia_id)
            ->when($bloquear, fn ($query) => $query->lockForUpdate())
            ->with('fuente')
            ->withSum('lineas as techo_asignado_pasiva', 'apropiacion_definitiva')
            ->withSum('lineas as techo_comprometido_pasiva', 'compromisos')
            ->withSum('lineas as techo_obligado_pasiva', 'obligaciones')
            ->withSum('lineas as techo_pagado_pasiva', 'pagos')
            ->get()
            ->keyBy('fuente_financiacion_id');
    }

    /**
     * @return Collection<int, Actividad>
     */
    private function actividadesDelReporte(ReporteProyecto $reporte): Collection
    {
        return Actividad::sinFiltroSectorial()
            ->where('proyecto_id', $reporte->proyecto_id)
            ->where('dependencia_id', $reporte->dependencia_id)
            ->get();
    }

    /**
     * @param  array<array-key, array{fuente_financiacion_id: int, comprometido: float, obligado: float, pagado: float}>  $todas
     * @param  \Illuminate\Support\Collection<int, Techo>  $techos
     * @param  array<array-key, array{fuente_financiacion_id: int, comprometido: float, obligado: float, pagado: float}>  $nuevas
     */
    private function validarContraTecho(ReporteProyecto $reporte, array $todas, \Illuminate\Support\Collection $techos, array $nuevas): void
    {
        $tolerancia = (float) config('reporte_sectorial.tolerancia_techo', 0);
        $rubros = [
            'comprometido' => ['label' => 'Comprometido', 'techo' => 'techo_comprometido_pasiva'],
            'obligado' => ['label' => 'Obligado', 'techo' => 'techo_obligado_pasiva'],
            'pagado' => ['label' => 'Pagado', 'techo' => 'techo_pagado_pasiva'],
        ];
        $totales = [];

        foreach ($todas as $fila) {
            foreach (array_keys($rubros) as $campo) {
                $totales[$fila['fuente_financiacion_id']][$campo] = ($totales[$fila['fuente_financiacion_id']][$campo] ?? 0) + (float) $fila[$campo];
            }
        }

        $errores = [];

        foreach ($totales as $fuenteId => $valores) {
            $techo = $techos->get($fuenteId);
            $fuente = $techo?->fuente ?? FuenteFinanciacion::query()->find($fuenteId);

            foreach ($rubros as $campo => $configuracion) {
                $total = (float) ($valores[$campo] ?? 0);
                $valorTecho = $techo === null ? 0.0 : (float) ($techo->{$configuracion['techo']} ?? 0);

                if ($total <= $valorTecho + $tolerancia) {
                    continue;
                }

                $errores['ejecucion.fuente.'.$fuenteId.'.'.$campo] = sprintf(
                    '%s — %s: el total reportado (%s) supera el techo de la pasiva (%s) por %s.',
                    $fuente?->etiqueta() ?? 'Fuente '.$fuenteId,
                    $configuracion['label'],
                    self::pesos($total),
                    self::pesos($valorTecho),
                    self::pesos($total - $valorTecho),
                );
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function validarProgramacionContraTecho(Seguimiento $seguimiento, Proyecto $proyecto, Dependencia $dependencia): void
    {
        $programado = ActividadProgramacion::sinFiltroSectorial()
            ->where('vigencia', $seguimiento->vigencia)
            ->whereHas('actividad', fn ($query) => $query->withoutGlobalScopes()->where('proyecto_id', $proyecto->id)->where('dependencia_id', $dependencia->id))
            ->groupBy('fuente_financiacion_id')
            ->selectRaw('fuente_financiacion_id, SUM(valor_asignado) as total')
            ->pluck('total', 'fuente_financiacion_id');

        $techos = Techo::sinFiltroSectorial()
            ->where('seguimiento_id', $seguimiento->id)
            ->where('proyecto_id', $proyecto->id)
            ->where('dependencia_id', $dependencia->id)
            ->with('fuente')
            ->withSum('lineas as techo_asignado_pasiva', 'apropiacion_definitiva')
            ->get()
            ->keyBy('fuente_financiacion_id');

        $errores = [];

        foreach ($programado as $fuenteId => $total) {
            $techo = $techos->get($fuenteId);
            $valorTecho = $techo === null ? 0.0 : (float) ($techo->techo_asignado_pasiva ?? 0);

            if ((float) $total > $valorTecho) {
                $fuente = $techo?->fuente ?? FuenteFinanciacion::query()->find($fuenteId);
                $errores['ejecucion.fuente.'.$fuenteId.'.programado'] = sprintf('%s — Asignado: el total reportado (%s) supera el techo asignado de la pasiva (%s) por %s.', $fuente?->etiqueta() ?? 'Fuente '.$fuenteId, self::pesos($total), self::pesos($valorTecho), self::pesos((float) $total - $valorTecho));
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Exige que cada meta producto haya sido guardada y que la distribución por fuente
     * coincida con Asignado, Comprometido, Obligado y Pagado de la pasiva.
     *
     * @param  \Illuminate\Support\Collection<int, Techo>  $techos
     */
    private function validarConciliacionFinanciera(ReporteProyecto $reporte, \Illuminate\Support\Collection $techos): void
    {
        $tolerancia = (float) config('reporte_sectorial.tolerancia_techo', 0.01);
        $actividades = $this->actividadesDelReporte($reporte);
        $ejecuciones = $reporte->exists
            ? $reporte->ejecuciones()->get()->keyBy(fn (EjecucionFinanciera $ejecucion): string => $ejecucion->actividad_id.'-'.$ejecucion->fuente_financiacion_id)
            : collect();
        $errores = [];

        foreach ($actividades as $actividad) {
            $completa = $techos->keys()->every(fn ($fuenteId): bool => $ejecuciones->has($actividad->id.'-'.$fuenteId));

            if (! $completa) {
                $errores['conciliacion.actividad.'.$actividad->id] = 'Falta guardar la ejecución financiera de '.$actividad->etiqueta().'.';
            }
        }

        $programadoPorFuente = ActividadProgramacion::sinFiltroSectorial()
            ->where('vigencia', $reporte->seguimiento->vigencia)
            ->whereHas('actividad', fn ($query) => $query->withoutGlobalScopes()->where('proyecto_id', $reporte->proyecto_id)->where('dependencia_id', $reporte->dependencia_id))
            ->groupBy('fuente_financiacion_id')
            ->selectRaw('fuente_financiacion_id, SUM(valor_asignado) as total')
            ->pluck('total', 'fuente_financiacion_id');
        $ejecucionPorFuente = $ejecuciones->groupBy('fuente_financiacion_id');
        $rubros = [
            'asignado' => ['label' => 'Programado', 'techo' => 'techo_asignado_pasiva'],
            'comprometido' => ['label' => 'Comprometido', 'techo' => 'techo_comprometido_pasiva'],
            'obligado' => ['label' => 'Obligado', 'techo' => 'techo_obligado_pasiva'],
            'pagado' => ['label' => 'Pagado', 'techo' => 'techo_pagado_pasiva'],
        ];

        foreach ($techos as $fuenteId => $techo) {
            foreach ($rubros as $campo => $configuracion) {
                $reportado = $campo === 'asignado'
                    ? (float) ($programadoPorFuente[$fuenteId] ?? 0)
                    : (float) $ejecucionPorFuente->get($fuenteId, collect())->sum($campo);
                $valorTecho = (float) ($techo->{$configuracion['techo']} ?? 0);
                $diferencia = $valorTecho - $reportado;

                if (abs($diferencia) <= $tolerancia) {
                    continue;
                }

                $errores['conciliacion.fuente.'.$fuenteId.'.'.$campo] = sprintf(
                    '%s — %s: el total reportado (%s) no coincide con el techo (%s); %s %s.',
                    $techo->fuente?->etiqueta() ?? 'Fuente '.$fuenteId,
                    $configuracion['label'],
                    self::pesos($reportado),
                    self::pesos($valorTecho),
                    $diferencia > 0 ? 'faltan' : 'excede por',
                    self::pesos(abs($diferencia)),
                );
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** @return array<string, array{programado: float, comprometido: float, obligado: float, pagado: float}> */
    private function totalesFinancierosDelReporte(ReporteProyecto $reporte, bool $porMeta): array
    {
        $metasRegionalizadas = $this->metasRegionalizadasIds($reporte);
        $programaciones = ActividadProgramacion::sinFiltroSectorial()
            ->where('vigencia', $reporte->seguimiento->vigencia)
            ->whereHas('actividad', fn ($query) => $query->withoutGlobalScopes()->where('proyecto_id', $reporte->proyecto_id)->where('dependencia_id', $reporte->dependencia_id))
            ->when($porMeta && $metasRegionalizadas !== null, fn ($query) => $query->whereHas('actividad', fn ($actividad) => $actividad->whereIn('meta_producto_id', $metasRegionalizadas)))
            ->get();
        $ejecuciones = $reporte->exists
            ? $reporte->ejecuciones()->when($porMeta && $metasRegionalizadas !== null, fn ($query) => $query->whereHas('actividad', fn ($actividad) => $actividad->whereIn('meta_producto_id', $metasRegionalizadas)))->get()
            : collect();
        $totales = [];
        $clave = fn (int $fuente, ?int $actividad): string => ($porMeta ? (string) ($actividad ?? 0) : 'proyecto').'-'.$fuente;

        foreach ($programaciones as $fila) {
            $key = $clave((int) $fila->fuente_financiacion_id, $porMeta ? (int) $fila->actividad_id : null);
            $totales[$key] ??= ['programado' => 0.0, 'comprometido' => 0.0, 'obligado' => 0.0, 'pagado' => 0.0];
            $totales[$key]['programado'] += (float) $fila->valor_asignado;
        }

        foreach ($ejecuciones as $fila) {
            $key = $clave((int) $fila->fuente_financiacion_id, $porMeta ? (int) $fila->actividad_id : null);
            $totales[$key] ??= ['programado' => 0.0, 'comprometido' => 0.0, 'obligado' => 0.0, 'pagado' => 0.0];
            foreach (['comprometido', 'obligado', 'pagado'] as $campo) {
                $totales[$key][$campo] += (float) $fila->{$campo};
            }
        }

        return $totales;
    }

    private function regionalizaPorMeta(ReporteProyecto $reporte): bool
    {
        return $reporte->proyecto->regionalizacion_modo === Proyecto::REGIONALIZACION_META
            || ($reporte->proyecto->regionalizacion_modo === null && $reporte->seguimiento->regionalizaPorMeta());
    }

    /** @return list<int>|null */
    private function metasRegionalizadasIds(ReporteProyecto $reporte): ?array
    {
        if ($reporte->proyecto->regionalizacion_modo !== Proyecto::REGIONALIZACION_META) {
            return null;
        }

        return $reporte->proyecto->metasProducto()
            ->wherePivot('regionaliza', true)
            ->pluck('metas_producto.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function actividadesParaDistribucion(ReporteProyecto $reporte)
    {
        $actividades = $this->actividadesDelReporte($reporte);
        $metas = $this->metasRegionalizadasIds($reporte);

        return $metas === null
            ? $actividades
            : $actividades->filter(fn (Actividad $actividad): bool => in_array((int) $actividad->meta_producto_id, $metas, true))->values();
    }

    private function validarDistribucionGuardada(ReporteProyecto $reporte, array $filas): void
    {
        $porMeta = $this->regionalizaPorMeta($reporte);
        $esperadosFuente = $this->totalesFinancierosDelReporte($reporte, $porMeta);
        $esperados = [];
        foreach ($esperadosFuente as $claveFuente => $valores) {
            $claveMeta = $porMeta ? explode('-', $claveFuente)[0] : 'proyecto';
            $esperados[$claveMeta] ??= ['programado' => 0.0, 'comprometido' => 0.0, 'obligado' => 0.0, 'pagado' => 0.0];
            foreach (['programado', 'comprometido', 'obligado', 'pagado'] as $campo) {
                $esperados[$claveMeta][$campo] += (float) $valores[$campo];
            }
        }
        $totales = [];
        foreach ($filas as $fila) {
            $key = $porMeta ? (string) ($fila['actividad_id'] ?? 0) : 'proyecto';
            $totales[$key] ??= ['programado' => 0.0, 'comprometido' => 0.0, 'obligado' => 0.0, 'pagado' => 0.0];
            foreach (['comprometido'] as $campo) {
                $totales[$key][$campo] += (float) ($fila[$campo] ?? 0);
            }
        }
        $tolerancia = (float) config('reporte_sectorial.tolerancia_techo', 0.01);
        $errores = [];
        foreach ($esperados as $key => $esperado) {
            foreach (['comprometido'] as $campo) {
                if (abs(($totales[$key][$campo] ?? 0) - $esperado[$campo]) > $tolerancia) {
                    $errores['distribucion.'.$key.'.'.$campo] = 'La distribución financiera no coincide con el total del corte.';
                }
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * @param  list<array{municipio_id: int|string, porcentaje: float|string, valor?: float|string|null, cantidad?: float|string|null}>  $filas
     */
    private function validarFocalizacion(ReporteProyecto $reporte, array $filas): void
    {
        $tolerancia = (float) config('reporte_sectorial.tolerancia_focalizacion_porcentaje', 0.01);
        $municipios = array_map(fn (array $fila): int => (int) $fila['municipio_id'], $filas);

        $permitidos = $reporte->proyecto->municipiosParaRegionalizacion()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($permitidos !== [] && array_diff($municipios, $permitidos) !== []) {
            throw ValidationException::withMessages(['focalizacion' => 'La focalización contiene municipios que no están configurados en el proyecto.']);
        }

        if (count($municipios) !== count(array_unique($municipios))) {
            throw ValidationException::withMessages(['focalizacion' => 'Hay municipios repetidos en la focalización.']);
        }

        $suma = array_sum(array_map(fn (array $fila): float => (float) $fila['porcentaje'], $filas));

        if (abs($suma - 100) > $tolerancia) {
            throw ValidationException::withMessages(['focalizacion' => sprintf('La focalización por municipio suma %s%%; debe sumar 100%%.', rtrim(rtrim(number_format($suma, 4, ',', '.'), '0'), ','))]);
        }

        $conValor = array_filter($filas, fn (array $fila): bool => isset($fila['valor']) && $fila['valor'] !== '' && $fila['valor'] !== null);

        if ($conValor === []) {
            return;
        }

        if (count($conValor) !== count($filas)) {
            throw ValidationException::withMessages(['focalizacion' => 'Si reporta valores por municipio, diligencie el valor de todos los municipios.']);
        }

        $sumaValores = array_sum(array_map(fn (array $fila): float => (float) $fila['valor'], $filas));
        $comprometido = $reporte->exists ? (float) $reporte->ejecuciones()->sum('comprometido') : 0.0;

        if (abs($sumaValores - $comprometido) > 1) {
            throw ValidationException::withMessages(['focalizacion' => sprintf('Los valores por municipio suman %s y deben sumar lo comprometido del reporte (%s).', self::pesos($sumaValores), self::pesos($comprometido))]);
        }
    }

    /**
     * @param  array<int, array{porcentaje: float, valor: float|null, cantidad: float|null}>  $previa
     * @param  list<array{municipio_id: int|string, porcentaje: float|string}>  $filas
     */
    private function cambioFocalizacion(array $previa, array $filas): bool
    {
        $tolerancia = (float) config('reporte_sectorial.tolerancia_focalizacion_porcentaje', 0.01);
        $nueva = [];

        foreach ($filas as $fila) {
            $nueva[(int) $fila['municipio_id']] = (float) $fila['porcentaje'];
        }

        if (array_diff_key($previa, $nueva) !== [] || array_diff_key($nueva, $previa) !== []) {
            return true;
        }

        foreach ($nueva as $municipioId => $porcentaje) {
            if (abs($porcentaje - $previa[$municipioId]['porcentaje']) > $tolerancia) {
                return true;
            }
        }

        return false;
    }
}
