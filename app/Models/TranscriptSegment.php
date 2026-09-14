<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TranscriptSegment extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function transcript()
    {
        return $this->belongsTo(Transcript::class);
    }

    public function sourceFile()
    {
        return $this->belongsTo(MeetingFile::class, 'meeting_file_id');
    }
}
