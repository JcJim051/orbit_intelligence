<?php

namespace App\Models\Suelos;

use Database\Factories\Suelos\ExploracionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * La geometría PointZ EPSG:9377 la calcula el disparador a partir de las coordenadas originales.
 */
class Exploracion extends Model
{
    /** @use HasFactory<ExploracionFactory> */
    use HasFactory;

    protected $table = 'suelos.exploracion';

    protected $fillable = [
        'estudio_id',
        'codigo',
        'tipo_exploracion_id',
        'x_original',
        'y_original',
        'sistema_coordenadas_id',
        'metodo_coordenadas_id',
        'cota_msnm',
        'profundidad_total_m',
        'nivel_freatico_m',
        'nivel_freatico_encontrado',
        'fecha_nivel_freatico',
        'fecha_ejecucion',
        'metodo_perforacion',
        'rechazo',
        'observaciones',
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
            'x_original' => 'decimal:4',
            'y_original' => 'decimal:4',
            'cota_msnm' => 'decimal:2',
            'profundidad_total_m' => 'decimal:2',
            'nivel_freatico_m' => 'decimal:2',
            'nivel_freatico_encontrado' => 'boolean',
            'fecha_nivel_freatico' => 'date',
            'fecha_ejecucion' => 'date',
            'rechazo' => 'boolean',
        ];
    }

    public function estudio(): BelongsTo
    {
        return $this->belongsTo(EstudioSuelos::class, 'estudio_id');
    }

    public function tipoExploracion(): BelongsTo
    {
        return $this->belongsTo(TipoExploracion::class);
    }

    public function sistemaCoordenadas(): BelongsTo
    {
        return $this->belongsTo(SistemaCoordenadas::class);
    }

    public function metodoCoordenadas(): BelongsTo
    {
        return $this->belongsTo(MetodoCoordenadas::class);
    }

    public function estratos(): HasMany
    {
        return $this->hasMany(Estrato::class);
    }

    public function ensayos(): HasMany
    {
        return $this->hasMany(Ensayo::class);
    }

    public function parametrosDiseno(): HasMany
    {
        return $this->hasMany(ParametrosDiseno::class);
    }

    /**
     * Exploraciones validadas a menos de $radioMetros del polígono más reciente del BPIN.
     * El radio por defecto es 500 m, valor de partida pendiente de ajuste con el revisor geotécnico.
     *
     * @return list<object>
     */
    public static function cercaDeProyecto(string $bpin, float $radioMetros = 500): array
    {
        return DB::select(
            'select * from suelos.fn_suelos_cerca_de_proyecto(?, ?)',
            [$bpin, $radioMetros],
        );
    }
}
