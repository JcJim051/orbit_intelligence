<?php

namespace App\Enums;

enum GeoViewerStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';
}
