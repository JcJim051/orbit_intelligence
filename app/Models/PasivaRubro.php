<?php

namespace App\Models;

use App\Services\Analitica\EsquemaAnalitica;
use Illuminate\Database\Eloquent\Model;

class PasivaRubro extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'corte',
        'unidad',
        'rubro',
        'concepto',
        'fuente_codigo',
        'fuente',
        'grupo_fuente',
        'sector',
        'bpin',
        'es_inversion',
        'definitiva',
        'certificado',
        'comprometido',
        'obligado',
        'pagado',
        'sha256',
        'pasiva_carga_id',
        'cargado_en',
        'origen',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'corte' => 'date',
            'definitiva' => 'decimal:2',
            'certificado' => 'decimal:2',
            'comprometido' => 'decimal:2',
            'obligado' => 'decimal:2',
            'pagado' => 'decimal:2',
            'cargado_en' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return EsquemaAnalitica::tabla('pasiva_rubro');
    }
}
