<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReporteProyectoTrazadorDistribucion extends Model
{
    protected $table = 'reporte_proyecto_trazador_distribuciones';

    protected $fillable = [
        'reporte_proyecto_id', 'proyecto_trazador_id', 'trazador_categoria_id',
        'fuente_financiacion_id', 'trazador_id', 'politica_publica_departamental_id',
        'asignado', 'comprometido', 'obligado', 'pagado',
    ];

    protected function casts(): array
    {
        return ['asignado' => 'decimal:2', 'comprometido' => 'decimal:2', 'obligado' => 'decimal:2', 'pagado' => 'decimal:2'];
    }

    public function reporte(): BelongsTo
    {
        return $this->belongsTo(ReporteProyecto::class, 'reporte_proyecto_id');
    }

    public function proyectoTrazador(): BelongsTo
    {
        return $this->belongsTo(ProyectoTrazador::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(TrazadorCategoria::class, 'trazador_categoria_id');
    }

    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteFinanciacion::class, 'fuente_financiacion_id');
    }

    public function trazador(): BelongsTo
    {
        return $this->belongsTo(Trazador::class);
    }

    public function politicaPublica(): BelongsTo
    {
        return $this->belongsTo(PoliticaPublicaDepartamental::class, 'politica_publica_departamental_id');
    }
}
