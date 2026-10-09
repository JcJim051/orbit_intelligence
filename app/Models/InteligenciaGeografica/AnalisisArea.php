<?php

namespace App\Models\InteligenciaGeografica;

use App\Enums\EstadoAnalisisArea;
use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Models\InvestmentProject;
use App\Models\User;
use Database\Factories\InteligenciaGeografica\AnalisisAreaFactory;
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
    ];

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
        ];
    }

    public function esOficial(): bool
    {
        return $this->modo === ModoAnalisis::Oficial;
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
     * Guarda el polígono dibujado o leído de un KMZ. GeoJSON en EPSG:4326; PostGIS lo deja en 9377.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function crearConGeoJson(array $attributes, string $geoJson4326): self
    {
        $analisis = new self($attributes);
        $now = now();
        $fecha = $analisis->fecha ?? $now;

        $id = DB::selectOne(
            'insert into inteligencia.analisis_area
                (investment_project_id, user_id, geom, origen_geometria, nombre_archivo, fecha, estado, modo, created_at, updated_at)
             values (
                ?,
                ?,
                ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 9377)), 3))::geometry(MultiPolygon, 9377),
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
             )
             returning id',
            [
                $analisis->investment_project_id,
                $analisis->user_id,
                $geoJson4326,
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
