<?php

namespace App\Models\Suelos;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Límite municipal en EPSG:9377 para validar coordenadas.
 * El catálogo de nombres sigue siendo public.municipios; la geometría MGN se carga aparte.
 */
class LimiteMunicipio extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'suelos.limite_municipio';

    protected $primaryKey = 'municipio_id';

    protected $fillable = [
        'municipio_id',
    ];

    protected $hidden = [
        'geom',
    ];

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
