<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaResultadoConstruccionComentario extends Model
{
    protected $table = 'meta_resultado_construccion_comentarios';

    protected $fillable = [
        'construccion_id',
        'user_id',
        'event_type',
        'comment',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function construccion(): BelongsTo
    {
        return $this->belongsTo(MetaResultadoConstruccion::class, 'construccion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
