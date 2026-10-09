<?php

namespace Database\Seeders;

use App\Models\Suelos\ClasificacionAashto;
use App\Models\Suelos\ClasificacionUscs;
use App\Models\Suelos\EstadoValidacion;
use App\Models\Suelos\MetodoCoordenadas;
use App\Models\Suelos\PerfilSueloNsr10;
use App\Models\Suelos\SistemaCoordenadas;
use App\Models\Suelos\TipoAdjunto;
use App\Models\Suelos\TipoCimentacion;
use App\Models\Suelos\TipoEnsayo;
use App\Models\Suelos\TipoEstudio;
use App\Models\Suelos\TipoExploracion;
use App\Models\Suelos\UnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Catálogos oficiales del esquema suelos.
 * Los municipios del Meta y las dependencias (AIM, EDESA y secretarías) ya viven
 * en public.municipios y public.dependencias; este seeder no los duplica.
 *
 * Los nombres y límites de perfil_suelo_nsr10 hay que verificarlos contra la tabla A.2.4-1 de la NSR-10.
 */
class SuelosCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        if (EstadoValidacion::query()->getConnection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('SuelosCatalogoSeeder requiere PostgreSQL con el esquema suelos.');
        }

        $this->filas(EstadoValidacion::class, [
            ['codigo' => 'CARGADO', 'nombre' => 'Cargado (borrador de la entidad)', 'es_final' => false, 'visible_en_informe' => false, 'orden' => 1],
            ['codigo' => 'EN_REVISION', 'nombre' => 'En revisión geotécnica', 'es_final' => false, 'visible_en_informe' => false, 'orden' => 2],
            ['codigo' => 'VALIDADO', 'nombre' => 'Validado', 'es_final' => true, 'visible_en_informe' => true, 'orden' => 3],
            ['codigo' => 'RECHAZADO', 'nombre' => 'Rechazado (devuelto con observaciones)', 'es_final' => false, 'visible_en_informe' => false, 'orden' => 4],
        ]);

        $this->filas(TipoEstudio::class, [
            ['codigo' => 'EDIF', 'nombre' => 'Estudio geotécnico para edificación'],
            ['codigo' => 'VIA', 'nombre' => 'Estudio de suelos para vía / pavimento'],
            ['codigo' => 'PUENTE', 'nombre' => 'Estudio geotécnico para puente / estructura'],
            ['codigo' => 'ACU', 'nombre' => 'Estudio geotécnico para acueducto / alcantarillado / PTAR'],
            ['codigo' => 'ESTAB', 'nombre' => 'Estudio de estabilidad de taludes'],
            ['codigo' => 'OTRO', 'nombre' => 'Otro'],
        ]);

        $this->filas(TipoExploracion::class, [
            ['codigo' => 'SONDEO', 'nombre' => 'Sondeo / perforación mecánica', 'orden' => 1],
            ['codigo' => 'SPT', 'nombre' => 'Sondeo con ensayo de penetración estándar (SPT)', 'orden' => 2],
            ['codigo' => 'APIQUE', 'nombre' => 'Apique', 'orden' => 3],
            ['codigo' => 'TRINCHERA', 'nombre' => 'Trinchera', 'orden' => 4],
            ['codigo' => 'CPT', 'nombre' => 'Ensayo de penetración de cono (CPT/CPTu)', 'orden' => 5],
            ['codigo' => 'PDC', 'nombre' => 'Penetrómetro dinámico de cono (PDC)', 'orden' => 6],
            ['codigo' => 'SEV', 'nombre' => 'Sondeo eléctrico vertical (geofísica)', 'orden' => 7],
            ['codigo' => 'SISMICA', 'nombre' => 'Línea sísmica de refracción / MASW (geofísica)', 'orden' => 8],
            ['codigo' => 'MANUAL', 'nombre' => 'Perforación manual (barreno)', 'orden' => 9],
        ]);

        $this->filas(ClasificacionUscs::class, [
            ['codigo' => 'GW', 'nombre' => 'Grava bien gradada', 'grupo' => 'GRUESO', 'orden' => 1],
            ['codigo' => 'GP', 'nombre' => 'Grava mal gradada', 'grupo' => 'GRUESO', 'orden' => 2],
            ['codigo' => 'GM', 'nombre' => 'Grava limosa', 'grupo' => 'GRUESO', 'orden' => 3],
            ['codigo' => 'GC', 'nombre' => 'Grava arcillosa', 'grupo' => 'GRUESO', 'orden' => 4],
            ['codigo' => 'SW', 'nombre' => 'Arena bien gradada', 'grupo' => 'GRUESO', 'orden' => 5],
            ['codigo' => 'SP', 'nombre' => 'Arena mal gradada', 'grupo' => 'GRUESO', 'orden' => 6],
            ['codigo' => 'SM', 'nombre' => 'Arena limosa', 'grupo' => 'GRUESO', 'orden' => 7],
            ['codigo' => 'SC', 'nombre' => 'Arena arcillosa', 'grupo' => 'GRUESO', 'orden' => 8],
            ['codigo' => 'ML', 'nombre' => 'Limo de baja plasticidad', 'grupo' => 'FINO', 'orden' => 9],
            ['codigo' => 'CL', 'nombre' => 'Arcilla de baja plasticidad', 'grupo' => 'FINO', 'orden' => 10],
            ['codigo' => 'MH', 'nombre' => 'Limo de alta plasticidad', 'grupo' => 'FINO', 'orden' => 11],
            ['codigo' => 'CH', 'nombre' => 'Arcilla de alta plasticidad', 'grupo' => 'FINO', 'orden' => 12],
            ['codigo' => 'OL', 'nombre' => 'Limo/arcilla orgánica de baja plasticidad', 'grupo' => 'ORGANICO', 'orden' => 13],
            ['codigo' => 'OH', 'nombre' => 'Arcilla/limo orgánico de alta plasticidad', 'grupo' => 'ORGANICO', 'orden' => 14],
            ['codigo' => 'PT', 'nombre' => 'Turba / suelo altamente orgánico', 'grupo' => 'ORGANICO', 'orden' => 15],
            ['codigo' => 'CL-ML', 'nombre' => 'Arcilla limosa', 'grupo' => 'DOBLE', 'orden' => 16],
            ['codigo' => 'SP-SM', 'nombre' => 'Arena mal gradada con limo', 'grupo' => 'DOBLE', 'orden' => 17],
            ['codigo' => 'SW-SM', 'nombre' => 'Arena bien gradada con limo', 'grupo' => 'DOBLE', 'orden' => 18],
            ['codigo' => 'SP-SC', 'nombre' => 'Arena mal gradada con arcilla', 'grupo' => 'DOBLE', 'orden' => 19],
            ['codigo' => 'SC-SM', 'nombre' => 'Arena arcillo-limosa', 'grupo' => 'DOBLE', 'orden' => 20],
            ['codigo' => 'GP-GM', 'nombre' => 'Grava mal gradada con limo', 'grupo' => 'DOBLE', 'orden' => 21],
            ['codigo' => 'GW-GM', 'nombre' => 'Grava bien gradada con limo', 'grupo' => 'DOBLE', 'orden' => 22],
            ['codigo' => 'GC-GM', 'nombre' => 'Grava arcillo-limosa', 'grupo' => 'DOBLE', 'orden' => 23],
        ]);

        $this->filas(ClasificacionAashto::class, [
            ['codigo' => 'A-1-a', 'nombre' => 'A-1-a Fragmentos de roca, grava y arena', 'orden' => 1],
            ['codigo' => 'A-1-b', 'nombre' => 'A-1-b Fragmentos de roca, grava y arena', 'orden' => 2],
            ['codigo' => 'A-3', 'nombre' => 'A-3 Arena fina', 'orden' => 3],
            ['codigo' => 'A-2-4', 'nombre' => 'A-2-4 Grava y arena limosa o arcillosa', 'orden' => 4],
            ['codigo' => 'A-2-5', 'nombre' => 'A-2-5 Grava y arena limosa o arcillosa', 'orden' => 5],
            ['codigo' => 'A-2-6', 'nombre' => 'A-2-6 Grava y arena limosa o arcillosa', 'orden' => 6],
            ['codigo' => 'A-2-7', 'nombre' => 'A-2-7 Grava y arena limosa o arcillosa', 'orden' => 7],
            ['codigo' => 'A-4', 'nombre' => 'A-4 Suelo limoso', 'orden' => 8],
            ['codigo' => 'A-5', 'nombre' => 'A-5 Suelo limoso', 'orden' => 9],
            ['codigo' => 'A-6', 'nombre' => 'A-6 Suelo arcilloso', 'orden' => 10],
            ['codigo' => 'A-7-5', 'nombre' => 'A-7-5 Suelo arcilloso', 'orden' => 11],
            ['codigo' => 'A-7-6', 'nombre' => 'A-7-6 Suelo arcilloso', 'orden' => 12],
        ]);

        // Verificar nombres y límites Vs30 contra la tabla A.2.4-1 de la NSR-10.
        $this->filas(PerfilSueloNsr10::class, [
            ['codigo' => 'A', 'nombre' => 'Perfil de roca competente', 'vs30_min_m_s' => 1500, 'vs30_max_m_s' => null],
            ['codigo' => 'B', 'nombre' => 'Perfil de roca de rigidez media', 'vs30_min_m_s' => 760, 'vs30_max_m_s' => 1500],
            ['codigo' => 'C', 'nombre' => 'Suelos muy densos o roca blanda', 'vs30_min_m_s' => 360, 'vs30_max_m_s' => 760],
            ['codigo' => 'D', 'nombre' => 'Perfiles de suelos rígidos', 'vs30_min_m_s' => 180, 'vs30_max_m_s' => 360],
            ['codigo' => 'E', 'nombre' => 'Perfiles de suelos blandos', 'vs30_min_m_s' => null, 'vs30_max_m_s' => 180],
            ['codigo' => 'F', 'nombre' => 'Suelos que requieren evaluación específica', 'vs30_min_m_s' => null, 'vs30_max_m_s' => null],
        ]);

        $this->filas(MetodoCoordenadas::class, [
            ['codigo' => 'GNSS_RTK', 'nombre' => 'GNSS diferencial / RTK', 'precision_estimada_m' => 0.05],
            ['codigo' => 'GNSS_NAV', 'nombre' => 'GPS navegador (código C/A)', 'precision_estimada_m' => 5],
            ['codigo' => 'EST_TOTAL', 'nombre' => 'Estación total amarrada a red geodésica', 'precision_estimada_m' => 0.05],
            ['codigo' => 'PLANO', 'nombre' => 'Digitalizado de plano de localización georreferenciado', 'precision_estimada_m' => 10],
            ['codigo' => 'PLANO_SG', 'nombre' => 'Ubicado sobre plano o imagen sin georreferenciación formal', 'precision_estimada_m' => 50],
            ['codigo' => 'APROX', 'nombre' => 'Ubicación aproximada (dirección, descripción)', 'precision_estimada_m' => 200],
        ]);

        $this->filas(SistemaCoordenadas::class, [
            ['codigo' => 'EPSG:9377', 'nombre' => 'MAGNA-SIRGAS / Origen-Nacional', 'srid' => 9377, 'observacion' => 'Recomendado'],
            ['codigo' => 'EPSG:4686', 'nombre' => 'MAGNA-SIRGAS geográficas (grados decimales)', 'srid' => 4686, 'observacion' => null],
            ['codigo' => 'EPSG:4326', 'nombre' => 'WGS 84 geográficas (grados decimales, GPS)', 'srid' => 4326, 'observacion' => null],
            ['codigo' => 'EPSG:3116', 'nombre' => 'MAGNA-SIRGAS / Colombia Bogotá zone', 'srid' => 3116, 'observacion' => 'Común en estudios anteriores a 2020'],
            ['codigo' => 'EPSG:3117', 'nombre' => 'MAGNA-SIRGAS / Colombia East Central zone', 'srid' => 3117, 'observacion' => 'Común en el oriente del Meta'],
        ]);

        $this->filas(UnidadMedida::class, [
            ['codigo' => 'PCT', 'nombre' => 'porcentaje', 'simbolo' => '%', 'magnitud' => 'adimensional', 'factor_a_base' => 1],
            ['codigo' => 'ADIM', 'nombre' => 'adimensional', 'simbolo' => '-', 'magnitud' => 'adimensional', 'factor_a_base' => 1],
            ['codigo' => 'GOLPES', 'nombre' => 'golpes por 30 cm', 'simbolo' => 'golpes/30 cm', 'magnitud' => 'conteo', 'factor_a_base' => 1],
            ['codigo' => 'KPA', 'nombre' => 'kilopascal', 'simbolo' => 'kPa', 'magnitud' => 'presión', 'factor_a_base' => 1],
            ['codigo' => 'MPA', 'nombre' => 'megapascal', 'simbolo' => 'MPa', 'magnitud' => 'presión', 'factor_a_base' => 1000],
            ['codigo' => 'KGCM2', 'nombre' => 'kilogramo-fuerza por centímetro cuadrado', 'simbolo' => 'kgf/cm²', 'magnitud' => 'presión', 'factor_a_base' => 98.0665],
            ['codigo' => 'TM2', 'nombre' => 'tonelada-fuerza por metro cuadrado', 'simbolo' => 'tf/m²', 'magnitud' => 'presión', 'factor_a_base' => 9.80665],
            ['codigo' => 'KNM3', 'nombre' => 'kilonewton por metro cúbico', 'simbolo' => 'kN/m³', 'magnitud' => 'peso unitario', 'factor_a_base' => 1],
            ['codigo' => 'GCM3', 'nombre' => 'gramo por centímetro cúbico (densidad)', 'simbolo' => 'g/cm³', 'magnitud' => 'peso unitario', 'factor_a_base' => 9.80665],
            ['codigo' => 'GRADO', 'nombre' => 'grado sexagesimal', 'simbolo' => '°', 'magnitud' => 'ángulo', 'factor_a_base' => 1],
            ['codigo' => 'M', 'nombre' => 'metro', 'simbolo' => 'm', 'magnitud' => 'longitud', 'factor_a_base' => 1],
            ['codigo' => 'CM', 'nombre' => 'centímetro', 'simbolo' => 'cm', 'magnitud' => 'longitud', 'factor_a_base' => 0.01],
            ['codigo' => 'MS', 'nombre' => 'metro por segundo', 'simbolo' => 'm/s', 'magnitud' => 'velocidad', 'factor_a_base' => 1],
            ['codigo' => 'OHMM', 'nombre' => 'ohmio-metro', 'simbolo' => 'Ω·m', 'magnitud' => 'resistividad', 'factor_a_base' => 1],
            ['codigo' => 'CM2KG', 'nombre' => 'centímetro cuadrado por kilogramo (coef. compresibilidad)', 'simbolo' => 'cm²/kg', 'magnitud' => 'compresibilidad', 'factor_a_base' => null],
        ]);

        $bases = [
            'presión' => 'KPA',
            'peso unitario' => 'KNM3',
            'longitud' => 'M',
        ];

        foreach ($bases as $magnitud => $codigoBase) {
            $base = UnidadMedida::query()->where('codigo', $codigoBase)->firstOrFail();
            UnidadMedida::query()
                ->where('magnitud', $magnitud)
                ->update(['unidad_base_id' => $base->id]);
        }

        $unidad = UnidadMedida::query()->pluck('id', 'codigo');

        $this->filas(TipoEnsayo::class, [
            ['codigo' => 'W_NAT', 'nombre' => 'Humedad natural', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 500],
            ['codigo' => 'LL', 'nombre' => 'Límite líquido', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 500],
            ['codigo' => 'LP', 'nombre' => 'Límite plástico', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 300],
            ['codigo' => 'IP', 'nombre' => 'Índice de plasticidad', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 300],
            ['codigo' => 'FINOS', 'nombre' => 'Granulometría: % que pasa tamiz N.º 200', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 100],
            ['codigo' => 'ARENA', 'nombre' => 'Granulometría: % arena', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 100],
            ['codigo' => 'GRAVA', 'nombre' => 'Granulometría: % grava', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 100],
            ['codigo' => 'GAMMA', 'nombre' => 'Peso unitario total', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['KNM3'], 'rango_min' => 5, 'rango_max' => 30],
            ['codigo' => 'GS', 'nombre' => 'Gravedad específica de sólidos', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['ADIM'], 'rango_min' => 1.5, 'rango_max' => 4],
            ['codigo' => 'SPT_N', 'nombre' => 'SPT — N de campo', 'ambito' => 'CAMPO', 'unidad_id' => $unidad['GOLPES'], 'rango_min' => 0, 'rango_max' => 100],
            ['codigo' => 'SPT_N60', 'nombre' => 'SPT — N60 corregido por energía (reportado)', 'ambito' => 'CAMPO', 'unidad_id' => $unidad['GOLPES'], 'rango_min' => 0, 'rango_max' => 150],
            ['codigo' => 'COHESION', 'nombre' => 'Cohesión (c o c\')', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['KPA'], 'rango_min' => 0, 'rango_max' => 1000],
            ['codigo' => 'PHI', 'nombre' => 'Ángulo de fricción interna', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['GRADO'], 'rango_min' => 0, 'rango_max' => 55],
            ['codigo' => 'QU', 'nombre' => 'Resistencia a la compresión inconfinada (qu)', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['KPA'], 'rango_min' => 0, 'rango_max' => 5000],
            ['codigo' => 'CBR_LAB', 'nombre' => 'CBR de laboratorio', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 100],
            ['codigo' => 'CBR_CAMPO', 'nombre' => 'CBR inalterado / de campo', 'ambito' => 'CAMPO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 100],
            ['codigo' => 'CC', 'nombre' => 'Índice de compresión (consolidación)', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['ADIM'], 'rango_min' => 0, 'rango_max' => 5],
            ['codigo' => 'CS', 'nombre' => 'Índice de recompresión / expansión (consolidación)', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['ADIM'], 'rango_min' => 0, 'rango_max' => 1],
            ['codigo' => 'SIGMA_P', 'nombre' => 'Presión de preconsolidación', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['KPA'], 'rango_min' => 0, 'rango_max' => 5000],
            ['codigo' => 'EXP_LIBRE', 'nombre' => 'Expansión libre', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['PCT'], 'rango_min' => 0, 'rango_max' => 300],
            ['codigo' => 'P_EXP', 'nombre' => 'Presión de expansión', 'ambito' => 'LABORATORIO', 'unidad_id' => $unidad['KPA'], 'rango_min' => 0, 'rango_max' => 2000],
            ['codigo' => 'VS', 'nombre' => 'Velocidad de onda de corte (Vs) del estrato', 'ambito' => 'CAMPO', 'unidad_id' => $unidad['MS'], 'rango_min' => 30, 'rango_max' => 3000],
            ['codigo' => 'RESIST', 'nombre' => 'Resistividad aparente (SEV)', 'ambito' => 'CAMPO', 'unidad_id' => $unidad['OHMM'], 'rango_min' => 0, 'rango_max' => 100000],
            ['codigo' => 'CPT_QC', 'nombre' => 'CPT — resistencia de punta qc', 'ambito' => 'CAMPO', 'unidad_id' => $unidad['MPA'], 'rango_min' => 0, 'rango_max' => 100],
        ]);

        $this->filas(TipoCimentacion::class, [
            ['codigo' => 'ZAP_AISL', 'nombre' => 'Zapatas aisladas'],
            ['codigo' => 'ZAP_CORR', 'nombre' => 'Zapatas corridas'],
            ['codigo' => 'LOSA', 'nombre' => 'Losa de cimentación'],
            ['codigo' => 'PILOTES', 'nombre' => 'Pilotes'],
            ['codigo' => 'CAISSONS', 'nombre' => 'Caissons / pilas'],
            ['codigo' => 'MEJORAMIENTO', 'nombre' => 'Mejoramiento o sustitución de suelo'],
            ['codigo' => 'OTRA', 'nombre' => 'Otra'],
        ]);

        $this->filas(TipoAdjunto::class, [
            ['codigo' => 'INFORME', 'nombre' => 'Informe del estudio (PDF firmado)', 'extensiones_permitidas' => 'pdf'],
            ['codigo' => 'REGISTROS', 'nombre' => 'Registros de perforación', 'extensiones_permitidas' => 'pdf,xlsx'],
            ['codigo' => 'LAB', 'nombre' => 'Resultados de laboratorio', 'extensiones_permitidas' => 'pdf,xlsx'],
            ['codigo' => 'PLANO', 'nombre' => 'Plano de localización de exploraciones', 'extensiones_permitidas' => 'pdf,dwg,kmz,kml'],
            ['codigo' => 'PLANTILLA', 'nombre' => 'Plantilla Excel SIID diligenciada', 'extensiones_permitidas' => 'xlsx'],
            ['codigo' => 'AGS', 'nombre' => 'Archivo de intercambio AGS / DIGGS', 'extensiones_permitidas' => 'ags,xml'],
        ]);
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  list<array<string, mixed>>  $filas
     */
    private function filas(string $modelo, array $filas): void
    {
        foreach ($filas as $fila) {
            $modelo::query()->updateOrCreate(
                ['codigo' => $fila['codigo']],
                $fila,
            );
        }
    }
}
