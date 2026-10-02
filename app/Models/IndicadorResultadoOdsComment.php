<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicadorResultadoOdsComment extends Model
{
    protected $fillable = ['review_id', 'link_id', 'user_id', 'event_type', 'comment', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(IndicadorResultadoOdsReview::class, 'review_id');
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(IndicadorResultadoOdsLink::class, 'link_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
