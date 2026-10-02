<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicadorResultadoOdsLink extends Model
{
    protected $fillable = [
        'review_id',
        'indicador_resultado_id',
        'ods_indicator_id',
        'relation_type',
        'confidence',
        'status',
        'justification',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(IndicadorResultadoOdsReview::class, 'review_id');
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorResultado::class, 'indicador_resultado_id');
    }

    public function odsIndicator(): BelongsTo
    {
        return $this->belongsTo(OdsIndicator::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IndicadorResultadoOdsComment::class, 'link_id');
    }
}
