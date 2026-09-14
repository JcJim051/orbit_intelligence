<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MeetingFile extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
