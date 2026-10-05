<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanIndicativoMeta extends Model
{
    protected $table = 'plan_indicativo_metas';

    protected $fillable = [
        'meta_producto_id',
        'vigencia',
        'valor_programado',
        'observacion',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vigencia' => 'integer',
            'valor_programado' => 'decimal:4',
        ];
    }

    public function metaProducto(): BelongsTo
    {
        return $this->belongsTo(MetaProducto::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
