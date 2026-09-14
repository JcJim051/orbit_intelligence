<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Transcript extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['speaker_map' => 'array', 'provider_metadata' => 'array'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function segments()
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('sequence');
    }
}
