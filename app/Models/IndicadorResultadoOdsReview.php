<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicadorResultadoOdsReview extends Model
{
    protected $fillable = ['indicador_resultado_id', 'assigned_to', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(IndicadorResultado::class, 'indicador_resultado_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function links(): HasMany
    {
        return $this->hasMany(IndicadorResultadoOdsLink::class, 'review_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IndicadorResultadoOdsComment::class, 'review_id')->latest();
    }
}
