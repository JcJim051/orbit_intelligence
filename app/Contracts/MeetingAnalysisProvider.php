<?php

namespace App\Contracts;

use App\Data\MeetingAnalysisResult;
use App\Models\Meeting;
use App\Models\Transcript;

interface MeetingAnalysisProvider
{
    public function analyze(Meeting $meeting, Transcript $transcript): MeetingAnalysisResult;

    public function name(): string;

    public function model(): string;
}
