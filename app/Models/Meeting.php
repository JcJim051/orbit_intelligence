<?php

namespace App\Models;

use App\Enums\DriveSyncStatus;
use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'held_at' => 'datetime',
            'participants' => 'array',
            'artifacts' => 'array',
            'drive_connection_ids' => 'array',
            'recording_consent_confirmed' => 'boolean',
            'consent_confirmed_at' => 'datetime',
            'approved_at' => 'datetime',
            'status' => MeetingStatus::class,
            'drive_sync_status' => DriveSyncStatus::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function files()
    {
        return $this->hasMany(MeetingFile::class);
    }

    public function transcript()
    {
        return $this->hasOne(Transcript::class);
    }

    public function summaries()
    {
        return $this->hasMany(MeetingSummary::class);
    }

    public function exports()
    {
        return $this->hasMany(DriveExport::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class)->latest('created_at');
    }

    public function investmentProjects(): BelongsToMany
    {
        return $this->belongsToMany(InvestmentProject::class, 'investment_project_meeting')
            ->withPivot(['id', 'agenda_reason', 'prepared_questions', 'added_by'])
            ->withTimestamps();
    }

    public function currentSummary(): ?MeetingSummary
    {
        return $this->summaries()->with('actionItems')->latest('version')->first();
    }
}
