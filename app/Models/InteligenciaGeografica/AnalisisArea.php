<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\EstadoAnalisisArea;
use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Models\InvestmentProject;
use App\Models\User;
use Database\Factories\InteligenciaGeografica\AnalisisAreaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AnalisisArea extends Model
{
    /** @use HasFactory<AnalisisAreaFactory> */
    use HasFactory;

    protected $table = 'inteligencia.analisis_area';

    protected $fillable = [
        'investment_project_id',
        'user_id',
        'origen_geometria',
        'nombre_archivo',
        'fecha',
        'estado',
        'modo',
    ];

    protected $hidden = [
        'geom',
        'geometria_original',
        'geometria_analizada',
        'mapa_imagen',
    ];

    /**
     * El PNG del mapa no entra en el SELECT habitual: el informe lo lee aparte.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('sin_mapa_imagen', function (Builder $consulta): void {
            if (DB::getDriverName() !== 'pgsql' || $consulta->getQuery()->columns !== null || $consulta->getQuery()->aggregate !== null) {
                return;
            }

            $consulta->select($consulta->qualifyColumns([
                'id',
                'investment_project_id',
                'user_id',
                'geom',
                'origen_geometria',
                'nombre_archivo',
                'fecha',
                'estado',
                'created_at',
                'updated_at',
                'modo',
                'geometria_original',
                'geometria_analizada',
                'ha_fuera_meta',
                'pct_fuera_meta',
                'mapa_capturado_at',
                'mapa_atribucion',
                'mapa_nota',
            ]));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origen_geometria' => OrigenGeometriaAnalisis::class,
            'fecha' => 'datetime',
            'estado' => EstadoAnalisisArea::class,
            'modo' => ModoAnalisis::class,
            'ha_fuera_meta' => 'decimal:4',
            'pct_fuera_meta' => 'decimal:2',
            'mapa_capturado_at' => 'datetime',
        ];
    }

    public function textoFueraDelMeta(): ?string
    {
        if ((float) $this->pct_fuera_meta <= 0) {
            return null;
        }

        $hectareas = number_format((float) $this->ha_fuera_meta, 2, ',', '.');
        $porcentaje = number_format((float) $this->pct_fuera_meta, 2, ',', '.');

        return "Quedaron fuera del Meta {$hectareas} ha ({$porcentaje}%). Esa parte no se analizó porque las capas cubren solo el Meta.";
    }

    public function esOficial(): bool
    {
        return $this->modo === ModoAnalisis::Oficial;
    }

    public function geoJson4326(): ?string
    {
        $fila = DB::selectOne(
            'select ST_AsGeoJSON(ST_Transform(geom, 4326)) as geojson from inteligencia.analisis_area where id = ?',
            [$this->id],
        );

        return $fila?->geojson;
    }

    public function areaHectareas(): ?float
    {
        $fila = DB::selectOne(
            'select ST_Area(geom) / 10000 as hectareas from inteligencia.analisis_area where id = ?',
            [$this->id],
        );

        return $fila === null ? null : round((float) $fila->hectareas, 2);
    }

    public function geoJsonFuera4326(): ?string
    {
        $fila = DB::selectOne(
            'select case
                when geometria_original is null or geometria_analizada is null then null
                when ST_IsEmpty(ST_CollectionExtract(ST_Difference(geometria_original, geometria_analizada), 3)) then null
                else ST_AsGeoJSON(ST_Transform(ST_Multi(ST_CollectionExtract(ST_Difference(geometria_original, geometria_analizada), 3)), 4326))
             end as geojson
             from inteligencia.analisis_area where id = ?',
            [$this->id],
        );

        return $fila?->geojson;
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(AnalisisResultado::class);
    }

    /**
     * Guarda el polígono. El GeoJSON llega en EPSG:4326 o EPSG:9377 y PostGIS lo deja en 9377.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array{ha_fuera: float, pct_fuera: float, geojson_original: string, geojson_analizada: string}|null  $recorte
     */
    public static function crearConGeoJson(array $attributes, string $geoJson4326, int $sridOrigen = 4326, ?array $recorte = null): self
    {
        if (! in_array($sridOrigen, [4326, 9377], true)) {
            throw new RuntimeException('El polígono solo puede llegar en EPSG:4326 o EPSG:9377.');
        }

        $analisis = new self($attributes);
        $now = now();
        $fecha = $analisis->fecha ?? $now;
        $expresion = 'ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), ?), 9377)), 3))::geometry(MultiPolygon, 9377)';
        $valores = [
            $analisis->investment_project_id,
            $analisis->user_id,
        ];

        if ($recorte === null) {
            $geometrias = "{$expresion}, {$expresion}, {$expresion}, 0, 0";
            array_push($valores, $geoJson4326, $sridOrigen, $geoJson4326, $sridOrigen, $geoJson4326, $sridOrigen);
        } else {
            $geometrias = "{$expresion}, {$expresion}, {$expresion}, ?, ?";
            array_push(
                $valores,
                $recorte['geojson_analizada'],
                9377,
                $recorte['geojson_original'],
                9377,
                $recorte['geojson_analizada'],
                9377,
                $recorte['ha_fuera'],
                $recorte['pct_fuera'],
            );
        }

        $id = DB::selectOne(
            "insert into inteligencia.analisis_area
                (investment_project_id, user_id, geom, geometria_original, geometria_analizada, ha_fuera_meta, pct_fuera_meta, origen_geometria, nombre_archivo, fecha, estado, modo, created_at, updated_at)
             values (
                ?,
                ?,
                {$geometrias},
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
             )
             returning id",
            [
                ...$valores,
                $analisis->origen_geometria instanceof OrigenGeometriaAnalisis
                    ? $analisis->origen_geometria->value
                    : $analisis->origen_geometria,
                $analisis->nombre_archivo,
                $fecha,
                $analisis->estado instanceof EstadoAnalisisArea
                    ? $analisis->estado->value
                    : ($analisis->estado ?? EstadoAnalisisArea::Borrador->value),
                $analisis->modo instanceof ModoAnalisis
                    ? $analisis->modo->value
                    : ($analisis->modo ?? ModoAnalisis::Normal->value),
                $now,
                $now,
            ],
        );

        if ($id === null) {
            throw new RuntimeException('No se pudo guardar el polígono del análisis.');
        }

        return self::query()->findOrFail($id->id);
    }
}
