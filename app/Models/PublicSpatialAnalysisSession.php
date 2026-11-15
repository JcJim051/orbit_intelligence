<?php

namespace App\Models;

use App\Models\InteligenciaGeografica\AnalisisArea;
use Database\Factories\PublicSpatialAnalysisSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PublicSpatialAnalysisSession extends Model
{
    /** @use HasFactory<PublicSpatialAnalysisSessionFactory> */
    use HasFactory, HasUlids;

    protected $table = 'inteligencia.analisis_sesion_publica';

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? $this->table : 'analisis_sesion_publica';
    }

    protected $guarded = [];

    protected $hidden = ['token_hash', 'ip_hash', 'geojson'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(AnalisisArea::class, 'analisis_area_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }

    public static function fromToken(string $token): ?self
    {
        return self::query()->where('token_hash', hash('sha256', $token))->first();
    }
}
