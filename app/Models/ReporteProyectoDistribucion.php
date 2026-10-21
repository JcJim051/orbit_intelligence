<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteProyectoDistribucion extends Model
{
    protected $table = 'reporte_proyecto_distribuciones';

    protected $fillable = [
        'reporte_proyecto_id', 'actividad_id', 'fuente_financiacion_id', 'municipio_id',
        'programado', 'comprometido', 'obligado', 'pagado',
    ];

    protected function casts(): array
    {
        return [
            'programado' => 'decimal:2', 'comprometido' => 'decimal:2',
            'obligado' => 'decimal:2', 'pagado' => 'decimal:2',
        ];
    }

    public function reporte(): BelongsTo { return $this->belongsTo(ReporteProyecto::class, 'reporte_proyecto_id'); }
    public function actividad(): BelongsTo { return $this->belongsTo(Actividad::class); }
    public function fuente(): BelongsTo { return $this->belongsTo(FuenteFinanciacion::class, 'fuente_financiacion_id'); }
    public function municipio(): BelongsTo { return $this->belongsTo(Municipio::class); }
}
