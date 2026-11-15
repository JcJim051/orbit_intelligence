<?php

namespace App\Models;

use Database\Factories\PublicToolPublicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PublicToolPublication extends Model
{
    /** @use HasFactory<PublicToolPublicationFactory> */
    use HasFactory;

    public const GEOSPATIAL_ANALYSIS = 'analisis_geoespacial';

    protected $table = 'siid_publicacion.herramientas_publicas';

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? $this->table : 'herramientas_publicas';
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['visible' => 'boolean', 'orden' => 'integer', 'publicado_at' => 'datetime'];
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function imageUrl(): ?string
    {
        return filled($this->imagen_path) ? Storage::disk('public')->url($this->imagen_path) : null;
    }

    public static function geospatialAnalysis(): self
    {
        return self::query()->firstOrCreate(
            ['codigo' => self::GEOSPATIAL_ANALYSIS],
            ['visible' => false, 'titulo' => 'Análisis geoespacial', 'orden' => 20],
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $tool): void {
            $tool->publicado_at = $tool->visible ? ($tool->publicado_at ?? now()) : null;
            $tool->publicado_por = auth()->id() ?: $tool->publicado_por;
        });
    }
}
