<?php

namespace App\Models;

use Database\Factories\IndicadorProductoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndicadorProducto extends Model
{
    /** @use HasFactory<IndicadorProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'indicadores_producto';

    protected $fillable = [
        'meta_producto_id',
        'codigo',
        'nombre',
        'unidad_medida',
        'tipo_acumulacion',
        'linea_base',
        'meta_cuatrienio',
        'fuente_verificacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linea_base' => 'decimal:4',
            'meta_cuatrienio' => 'decimal:4',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (IndicadorProducto $indicador): void {
            $indicador->metaProducto()
                ->withoutGlobalScopes()
                ->update(['indicador_producto' => $indicador->nombre]);
        });

        static::deleted(function (IndicadorProducto $indicador): void {
            $indicador->metaProducto()
                ->withoutGlobalScopes()
                ->update(['indicador_producto' => null]);
        });
    }

    public function metaProducto(): BelongsTo
    {
        return $this->belongsTo(MetaProducto::class, 'meta_producto_id');
    }
}
