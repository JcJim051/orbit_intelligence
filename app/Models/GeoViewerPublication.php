<?php

namespace App\Models;

use App\Enums\GeoViewerStatus;
use Database\Factories\GeoViewerPublicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GeoViewerPublication extends Model
{
    /** @use HasFactory<GeoViewerPublicationFactory> */
    use HasFactory;

    protected $table = 'siid_publicacion.publicaciones_geovisores';

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? $this->table : 'publicaciones_geovisores';
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'destacado' => 'boolean',
            'orden' => 'integer',
            'publicado_at' => 'datetime',
        ];
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(GeoViewer::class, 'geo_viewer_id');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function imageUrl(): ?string
    {
        return filled($this->imagen_path) ? Storage::disk('public')->url($this->imagen_path) : null;
    }

    public function setVisibleAttribute(mixed $value): void
    {
        $visible = filter_var($value, FILTER_VALIDATE_BOOL);
        if ($visible && filled($this->attributes['geo_viewer_id'] ?? null)) {
            $status = GeoViewer::query()->whereKey($this->attributes['geo_viewer_id'])->value('status');
            $status = $status instanceof GeoViewerStatus ? $status : GeoViewerStatus::tryFrom((string) $status);
            if ($status !== GeoViewerStatus::Published) {
                throw ValidationException::withMessages([
                    'visible' => 'SIG debe aprobar técnicamente el geovisor antes de publicarlo.',
                ]);
            }
        }

        $this->attributes['visible'] = $visible;
    }

    protected static function booted(): void
    {
        static::saving(function (self $publication): void {
            $publication->publicado_at = $publication->visible ? ($publication->publicado_at ?? now()) : null;
            $publication->publicado_por = auth()->id() ?: $publication->publicado_por;
        });

        static::created(fn (self $publication) => $publication->recordAudit('creado', null));
        static::updated(function (self $publication): void {
            $changes = array_intersect_key($publication->getChanges(), array_flip(self::auditableFields()));

            if ($changes !== []) {
                $publication->recordAudit('actualizado', array_intersect_key($publication->getOriginal(), $changes));
            }
        });
    }

    /** @return array<int, string> */
    private static function auditableFields(): array
    {
        return ['visible', 'destacado', 'orden', 'titulo_publico', 'descripcion_publica', 'imagen_path'];
    }

    /** @param array<string, mixed>|null $before */
    private function recordAudit(string $event, ?array $before): void
    {
        $table = DB::getDriverName() === 'pgsql'
            ? 'siid_publicacion.publicaciones_geovisores_auditoria'
            : 'publicaciones_geovisores_auditoria';

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->insert([
            'publicacion_geovisor_id' => $this->getKey(),
            'usuario_id' => auth()->id(),
            'evento' => $event,
            'antes' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'despues' => json_encode($this->only(self::auditableFields()), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
