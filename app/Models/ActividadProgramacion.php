<?php

namespace App\Models;

use App\Models\Concerns\PerteneceADependencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor asignado a una actividad por fuente de financiación y vigencia.
 */
class ActividadProgramacion extends Model
{
    use PerteneceADependencia;

    protected $table = 'actividad_programaciones';

    protected $fillable = [
        'actividad_id',
        'fuente_financiacion_id',
        'vigencia',
        'valor_asignado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vigencia' => 'integer',
            'valor_asignado' => 'decimal:2',
        ];
    }

    protected function rutaDependencia(): ?string
    {
        return 'actividad';
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_financiacion_id');
    }
}
