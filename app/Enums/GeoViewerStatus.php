<?php

namespace App\Enums;

enum GeoViewerStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
