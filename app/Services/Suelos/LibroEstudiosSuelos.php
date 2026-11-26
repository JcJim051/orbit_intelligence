<?php

namespace App\Services\Suelos;

use App\Models\Dependencia;
use App\Models\InvestmentProject;
use App\Models\Municipio;
use App\Models\Suelos\ClasificacionAashto;
use App\Models\Suelos\ClasificacionUscs;
use App\Models\Suelos\EstadoValidacion;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\Exploracion;
use App\Models\Suelos\MetodoCoordenadas;
use App\Models\Suelos\PerfilSueloNsr10;
use App\Models\Suelos\SistemaCoordenadas;
use App\Models\Suelos\TipoCimentacion;
use App\Models\Suelos\TipoEnsayo;
use App\Models\Suelos\TipoEstudio;
use App\Models\Suelos\TipoExploracion;
use App\Models\Suelos\UnidadMedida;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

class LibroEstudiosSuelos
{
    /**
     * @var array<string, list<string>>
     */
    public const HOJAS = [
        'Estudio' => ['codigo', 'titulo', 'tipo_estudio', 'dependencia', 'bpin', 'sin_bpin_justificacion', 'consultor_nombre', 'consultor_nit', 'ingeniero_responsable', 'matricula_profesional', 'fecha_estudio', 'municipio'],
        'Exploraciones' => ['estudio', 'codigo', 'tipo_exploracion', 'x_original', 'y_original', 'sistema_coordenadas', 'metodo_coordenadas', 'cota_msnm', 'profundidad_total_m', 'nivel_freatico_encontrado', 'nivel_freatico_m'],
        'Estratos' => ['estudio', 'exploracion', 'profundidad_desde_m', 'profundidad_hasta_m', 'clasificacion_uscs', 'clasificacion_aashto', 'descripcion', 'es_relleno'],
        'Ensayos' => ['estudio', 'exploracion', 'tipo_ensayo', 'profundidad_desde_m', 'profundidad_hasta_m', 'valor', 'unidad_original', 'es_rechazo'],
        'Parametros' => ['estudio', 'exploracion', 'perfil_suelo_nsr10', 'vs30_m_s', 'capacidad_portante_adm_kpa', 'profundidad_desplante_m', 'tipo_cimentacion', 'potencial_expansivo', 'potencial_licuacion', 'recomendaciones'],
    ];

    public function plantilla(string $ruta): void
    {
        $writer = new Writer;
        $writer->openToFile($ruta);
        $primero = true;

        foreach (self::HOJAS as $nombre => $encabezados) {
            if ($primero) {
                $writer->getCurrentSheet()->setName($nombre);
                $primero = false;
            } else {
                $writer->addNewSheetAndMakeItCurrent()->setName($nombre);
            }

            $writer->addRow(Row::fromValues($encabezados));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');
        $writer->addRow(Row::fromValues(['Escriba códigos, no nombres. BPIN vacío exige sin_bpin_justificacion de al menos 20 caracteres.']));
        $writer->addRow(Row::fromValues(['Las coordenadas usan el sistema declarado. EPSG:4326 espera longitud y latitud.']));
        $writer->addRow(Row::fromValues(['Si una fila falla, no se guarda ningún estudio del archivo.']));
        $writer->close();
    }

    public function importar(string $ruta, User $usuario): void
    {
        $hojas = $this->leer($ruta);
        $errores = [];

        foreach (self::HOJAS as $nombre => $encabezados) {
            if (! isset($hojas[$nombre])) {
                $errores[] = 'Falta la hoja '.$nombre.'.';

                continue;
            }

            if ($hojas[$nombre]['headers'] !== $encabezados) {
                $errores[] = 'La hoja '.$nombre.' debe empezar con: '.implode(', ', $encabezados).'.';
            }
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['archivo' => implode(' ', $errores)]);
        }

        $estado = EstadoValidacion::query()->where('codigo', 'CARGADO')->firstOrFail();

        try {
            DB::transaction(function () use ($hojas, $usuario, $estado): void {
                $estudios = [];

                foreach ($hojas['Estudio']['rows'] as $fila) {
                    if ($this->vacia($fila['values'])) {
                        continue;
                    }

                    $valores = $fila['values'];
                    $tipo = TipoEstudio::query()->where('codigo', $this->texto($valores, 'tipo_estudio'))->first();
                    $dependencia = Dependencia::query()->where('codigo', $this->texto($valores, 'dependencia'))->first();
                    $municipio = Municipio::query()->where('codigo_dane', $this->texto($valores, 'municipio'))->first();
                    $bpin = $this->texto($valores, 'bpin');
                    $proyecto = $bpin === '' ? null : InvestmentProject::query()->where('bpin', $bpin)->first();

                    if ($tipo === null || $dependencia === null || $municipio === null || ($bpin !== '' && $proyecto === null)) {
                        throw ValidationException::withMessages([
                            'archivo' => 'Fila '.$fila['line'].' de Estudio: revise tipo, dependencia, municipio y BPIN.',
                        ]);
                    }

                    $estudio = EstudioSuelos::query()->create([
                        'codigo' => $this->texto($valores, 'codigo'),
                        'titulo' => $this->texto($valores, 'titulo'),
                        'tipo_estudio_id' => $tipo->id,
                        'dependencia_id' => $dependencia->id,
                        'investment_project_id' => $proyecto?->id,
                        'sin_bpin_justificacion' => $this->nulo($valores, 'sin_bpin_justificacion'),
                        'consultor_nombre' => $this->texto($valores, 'consultor_nombre'),
                        'consultor_nit' => $this->nulo($valores, 'consultor_nit'),
                        'ingeniero_responsable' => $this->nulo($valores, 'ingeniero_responsable'),
                        'matricula_profesional' => $this->nulo($valores, 'matricula_profesional'),
                        'fecha_estudio' => $this->texto($valores, 'fecha_estudio'),
                        'municipio_id' => $municipio->id,
                        'estado_validacion_id' => $estado->id,
                        'cargado_por' => $usuario->id,
                    ]);
                    $estudios[$estudio->codigo] = $estudio;
                }

                $exploraciones = [];

                foreach ($hojas['Exploraciones']['rows'] as $fila) {
                    if ($this->vacia($fila['values'])) {
                        continue;
                    }

                    $valores = $fila['values'];
                    $estudio = $estudios[$this->texto($valores, 'estudio')] ?? null;
                    $tipo = TipoExploracion::query()->where('codigo', $this->texto($valores, 'tipo_exploracion'))->first();
                    $sistema = SistemaCoordenadas::query()->where('codigo', $this->texto($valores, 'sistema_coordenadas'))->first();
                    $metodo = MetodoCoordenadas::query()->where('codigo', $this->texto($valores, 'metodo_coordenadas'))->first();
                    $encontro = $this->booleano($valores, 'nivel_freatico_encontrado');

                    if ($estudio === null || $tipo === null || $sistema === null || $metodo === null) {
                        throw ValidationException::withMessages([
                            'archivo' => 'Fila '.$fila['line'].' de Exploraciones: revise el estudio y los catálogos.',
                        ]);
                    }

                    $exploracion = Exploracion::query()->create([
                        'estudio_id' => $estudio->id,
                        'codigo' => $this->texto($valores, 'codigo'),
                        'tipo_exploracion_id' => $tipo->id,
                        'x_original' => $valores['x_original'],
                        'y_original' => $valores['y_original'],
                        'sistema_coordenadas_id' => $sistema->id,
                        'metodo_coordenadas_id' => $metodo->id,
                        'cota_msnm' => $this->nulo($valores, 'cota_msnm'),
                        'profundidad_total_m' => $valores['profundidad_total_m'],
                        'nivel_freatico_encontrado' => $encontro,
                        'nivel_freatico_m' => $encontro ? $valores['nivel_freatico_m'] : null,
                    ]);
                    $exploraciones[$estudio->codigo.'|'.$exploracion->codigo] = $exploracion;
                }

                foreach ($hojas['Estratos']['rows'] as $fila) {
                    if ($this->vacia($fila['values'])) {
                        continue;
                    }

                    $valores = $fila['values'];
                    $exploracion = $exploraciones[$this->texto($valores, 'estudio').'|'.$this->texto($valores, 'exploracion')] ?? null;
                    $uscs = $this->catalogo(ClasificacionUscs::class, $this->texto($valores, 'clasificacion_uscs'));
                    $aashto = $this->catalogo(ClasificacionAashto::class, $this->texto($valores, 'clasificacion_aashto'));

                    if ($exploracion === null) {
                        throw ValidationException::withMessages([
                            'archivo' => 'Fila '.$fila['line'].' de Estratos: la exploración no está en el archivo.',
                        ]);
                    }

                    $exploracion->estratos()->create([
                        'profundidad_desde_m' => $valores['profundidad_desde_m'],
                        'profundidad_hasta_m' => $valores['profundidad_hasta_m'],
                        'clasificacion_uscs_id' => $uscs,
                        'clasificacion_aashto_id' => $aashto,
                        'descripcion' => $this->texto($valores, 'descripcion'),
                        'es_relleno' => $this->booleano($valores, 'es_relleno'),
                    ]);
                }

                foreach ($hojas['Ensayos']['rows'] as $fila) {
                    if ($this->vacia($fila['values'])) {
                        continue;
                    }

                    $valores = $fila['values'];
                    $exploracion = $exploraciones[$this->texto($valores, 'estudio').'|'.$this->texto($valores, 'exploracion')] ?? null;
                    $tipo = TipoEnsayo::query()->where('codigo', $this->texto($valores, 'tipo_ensayo'))->first();
                    $unidad = $this->catalogo(UnidadMedida::class, $this->texto($valores, 'unidad_original'));

                    if ($exploracion === null || $tipo === null) {
                        throw ValidationException::withMessages([
                            'archivo' => 'Fila '.$fila['line'].' de Ensayos: revise la exploración y el tipo de ensayo.',
                        ]);
                    }

                    $exploracion->ensayos()->create([
                        'profundidad_desde_m' => $valores['profundidad_desde_m'],
                        'profundidad_hasta_m' => $this->nulo($valores, 'profundidad_hasta_m'),
                        'tipo_ensayo_id' => $tipo->id,
                        'valor' => $valores['valor'],
                        'unidad_original_id' => $unidad,
                        'es_rechazo' => $this->booleano($valores, 'es_rechazo'),
                    ]);
                }

                foreach ($hojas['Parametros']['rows'] as $fila) {
                    if ($this->vacia($fila['values'])) {
                        continue;
                    }

                    $valores = $fila['values'];
                    $estudio = $estudios[$this->texto($valores, 'estudio')] ?? null;
                    $exploracion = $exploraciones[$this->texto($valores, 'estudio').'|'.$this->texto($valores, 'exploracion')] ?? null;

                    if ($estudio === null) {
                        throw ValidationException::withMessages([
                            'archivo' => 'Fila '.$fila['line'].' de Parametros: el estudio no está en el archivo.',
                        ]);
                    }

                    $estudio->parametrosDiseno()->create([
                        'exploracion_id' => $exploracion?->id,
                        'perfil_suelo_nsr10_id' => $this->catalogo(PerfilSueloNsr10::class, $this->texto($valores, 'perfil_suelo_nsr10')),
                        'vs30_m_s' => $this->nulo($valores, 'vs30_m_s'),
                        'capacidad_portante_adm_kpa' => $this->nulo($valores, 'capacidad_portante_adm_kpa'),
                        'profundidad_desplante_m' => $this->nulo($valores, 'profundidad_desplante_m'),
                        'tipo_cimentacion_id' => $this->catalogo(TipoCimentacion::class, $this->texto($valores, 'tipo_cimentacion')),
                        'potencial_expansivo' => $this->nulo($valores, 'potencial_expansivo'),
                        'potencial_licuacion' => $this->nulo($valores, 'potencial_licuacion'),
                        'recomendaciones' => $this->nulo($valores, 'recomendaciones'),
                    ]);
                }
            });
        } catch (QueryException $excepcion) {
            throw ValidationException::withMessages([
                'archivo' => 'No se importó ningún estudio. '.$excepcion->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, array{headers: list<string>, rows: list<array{line: int, values: array<string, mixed>}>}>
     */
    private function leer(string $ruta): array
    {
        $reader = new Reader;
        $reader->open($ruta);
        $hojas = [];

        foreach ($reader->getSheetIterator() as $hoja) {
            $encabezados = null;
            $filas = [];
            $linea = 0;

            foreach ($hoja->getRowIterator() as $row) {
                $linea++;
                $valores = $row->toArray();

                if ($encabezados === null) {
                    $encabezados = array_map(fn (mixed $valor): string => trim((string) $valor), $valores);

                    continue;
                }

                $assoc = [];

                foreach ($encabezados as $indice => $encabezado) {
                    if ($encabezado === '') {
                        continue;
                    }

                    $assoc[$encabezado] = $valores[$indice] ?? null;
                }

                $filas[] = ['line' => $linea, 'values' => $assoc];
            }

            $hojas[$hoja->getName()] = [
                'headers' => array_values(array_filter($encabezados ?? [])),
                'rows' => $filas,
            ];
        }

        $reader->close();

        return $hojas;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function vacia(array $valores): bool
    {
        return collect($valores)->every(fn (mixed $valor): bool => trim((string) $valor) === '');
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function texto(array $valores, string $clave): string
    {
        return trim((string) ($valores[$clave] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function nulo(array $valores, string $clave): ?string
    {
        $texto = $this->texto($valores, $clave);

        return $texto === '' ? null : $texto;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function booleano(array $valores, string $clave): bool
    {
        return in_array(mb_strtolower($this->texto($valores, $clave)), ['1', 'true', 'si', 'sí'], true);
    }

    /**
     * @param  class-string  $modelo
     */
    private function catalogo(string $modelo, string $codigo): ?int
    {
        if ($codigo === '') {
            return null;
        }

        return $modelo::query()->where('codigo', $codigo)->first()?->getKey();
    }
}
