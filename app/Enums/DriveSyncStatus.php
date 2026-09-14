<?php

namespace App\Enums;

enum DriveSyncStatus: string
{
    case NotRequested = 'not_requested';
    case Pending = 'pending';
    case Partial = 'partial';
    case Synced = 'synced';
    case Error = 'error';
}
