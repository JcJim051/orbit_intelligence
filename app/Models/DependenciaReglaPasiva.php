<?php

namespace App\Models;

use App\Enums\TipoReglaPasiva;
use Database\Factories\DependenciaReglaPasivaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DependenciaReglaPasiva extends Model
{
    /** @use HasFactory<DependenciaReglaPasivaFactory> */
    use HasFactory;

    protected $table = 'dependencia_reglas_pasiva';

    protected $fillable = [
        'dependencia_id',
        'tipo_regla',
        'valor',
        'prioridad',
        'vigencia_desde',
        'vigencia_hasta',
        'observacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_regla' => TipoReglaPasiva::class,
            'prioridad' => 'integer',
            'vigencia_desde' => 'integer',
            'vigencia_hasta' => 'integer',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DependenciaReglaPasiva $regla): void {
            if ($regla->prioridad !== null) {
                return;
            }

            $tipo = $regla->tipo_regla instanceof TipoReglaPasiva
                ? $regla->tipo_regla
                : TipoReglaPasiva::tryFrom((string) $regla->tipo_regla);

            if ($tipo instanceof TipoReglaPasiva) {
                $regla->prioridad = $tipo->prioridadPorDefecto();
            }
        });
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }
}
