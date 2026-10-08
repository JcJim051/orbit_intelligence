<?php

namespace App\Models;

use App\Enums\OrigenCambioTecho;
use App\Models\Concerns\PerteneceADependencia;
use App\Models\Concerns\SeCongelaAlCerrarSeguimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora inmutable de cada cambio de techo: quién, cuándo, por qué, con qué carga o soporte.
 */
class TechoHistorial extends Model
{
    use PerteneceADependencia, SeCongelaAlCerrarSeguimiento;

    public const UPDATED_AT = null;

    protected $table = 'techo_historial';

    protected $fillable = [
        'techo_id',
        'seguimiento_id',
        'dependencia_id',
        'valor_anterior',
        'valor_nuevo',
        'comprometido_anterior',
        'comprometido_nuevo',
        'obligado_anterior',
        'obligado_nuevo',
        'pagado_anterior',
        'pagado_nuevo',
        'origen',
        'motivo',
        'pasiva_carga_id',
        'soporte_disk',
        'soporte_path',
        'soporte_nombre_original',
        'soporte_sha256',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_anterior' => 'decimal:2',
            'valor_nuevo' => 'decimal:2',
            'comprometido_anterior' => 'decimal:2',
            'comprometido_nuevo' => 'decimal:2',
            'obligado_anterior' => 'decimal:2',
            'obligado_nuevo' => 'decimal:2',
            'pagado_anterior' => 'decimal:2',
            'pagado_nuevo' => 'decimal:2',
            'origen' => OrigenCambioTecho::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
    }

    public function seguimientoIdParaCongelamiento(): ?int
    {
        return $this->seguimiento_id;
    }

    public function techo(): BelongsTo
    {
        return $this->belongsTo(Techo::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
