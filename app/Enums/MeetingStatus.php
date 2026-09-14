<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Received = 'received';
    case PendingTranscription = 'pending_transcription';
    case Transcribing = 'transcribing';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recibida',
            self::PendingTranscription => 'Pendiente de transcripción',
            self::Transcribing => 'Transcribiendo',
            self::PendingReview => 'Pendiente de revisión',
            self::Approved => 'Aprobada',
            self::Error => 'Error',
        };
    }
}
