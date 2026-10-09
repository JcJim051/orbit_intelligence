<?php

namespace App\Models\Suelos;

use App\Models\Dependencia;
use App\Models\InvestmentContract;
use App\Models\InvestmentProject;
use App\Models\Municipio;
use App\Models\User;
use Database\Factories\Suelos\EstudioSuelosFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Estudio de suelos. La entidad contratante es una dependencia existente.
 * El BPIN, cuando existe, es investment_projects (ahí cuelgan los contratos SECOP).
 * Un estudio sin proyecto sincronizado exige sin_bpin_justificacion de al menos 20 caracteres.
 */
class EstudioSuelos extends Model
{
    /** @use HasFactory<EstudioSuelosFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'suelos.estudio_suelos';

    protected $fillable = [
        'codigo',
        'titulo',
        'tipo_estudio_id',
        'dependencia_id',
        'investment_project_id',
        'investment_contract_id',
        'sin_bpin_justificacion',
        'consultor_nombre',
        'consultor_nit',
        'ingeniero_responsable',
        'matricula_profesional',
        'fecha_estudio',
        'fecha_exploracion_ini',
        'fecha_exploracion_fin',
        'municipio_id',
        'descripcion_sitio',
        'estado_validacion_id',
        'revisado_por',
        'fecha_revision',
        'observacion_revision',
        'cargado_por',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_estudio' => 'date',
            'fecha_exploracion_ini' => 'date',
            'fecha_exploracion_fin' => 'date',
            'fecha_revision' => 'datetime',
        ];
    }

    public function tipoEstudio(): BelongsTo
    {
        return $this->belongsTo(TipoEstudio::class);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(InvestmentProject::class, 'investment_project_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(InvestmentContract::class, 'investment_contract_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function estadoValidacion(): BelongsTo
    {
        return $this->belongsTo(EstadoValidacion::class);
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function exploraciones(): HasMany
    {
        return $this->hasMany(Exploracion::class, 'estudio_id');
    }

    public function estratos(): HasManyThrough
    {
        return $this->hasManyThrough(Estrato::class, Exploracion::class, 'estudio_id', 'exploracion_id');
    }

    public function parametrosDiseno(): HasMany
    {
        return $this->hasMany(ParametrosDiseno::class, 'estudio_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(Adjunto::class, 'estudio_id');
    }

    public function historialValidacion(): HasMany
    {
        return $this->hasMany(EstudioValidacionHistorial::class, 'estudio_id');
    }

    public function scopeDeDependencia(Builder $query, int $dependenciaId): void
    {
        $query->where('dependencia_id', $dependenciaId);
    }

    public function scopeVisibleEnInforme(Builder $query): void
    {
        $query->whereHas(
            'estadoValidacion',
            fn (Builder $estado) => $estado->where('visible_en_informe', true),
        );
    }
}
