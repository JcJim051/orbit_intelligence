<?php

namespace App\Enums;

enum ActionItemStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
