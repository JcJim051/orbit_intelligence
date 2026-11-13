<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SiidPublicacion extends Model
{
    protected $table = 'siid_publicacion.publicaciones';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean', 'destacado' => 'boolean', 'orden' => 'integer',
            'mostrar_serie' => 'boolean',
            'publicado_at' => 'datetime',
        ];
    }

    public function publicable(): MorphTo
    {
        return $this->morphTo();
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function scopePublicas($query)
    {
        return $query->where('visible', true)->orderBy('orden');
    }

    public function publicableIsActive(): bool
    {
        $item = $this->publicable;

        return $item instanceof SiidIndicador
            ? (bool) $item->activo
            : ($item instanceof IndicadorTerritorial && (bool) $item->activo && $item->deleted_at === null);
    }

    public function imageUrl(): ?string
    {
        return filled($this->imagen_path) ? Storage::disk('public')->url($this->imagen_path) : null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $publication): void {
            $item = $publication->publicable;
            if ($publication->visible && (! $item || ! (bool) ($item->activo ?? false))) {
                throw ValidationException::withMessages(['visible' => 'Un indicador inactivo no puede publicarse.']);
            }
        });
    }
}
