<?php

namespace App\Data;

final readonly class MeetingAnalysisResult
{
    public function __construct(
        public string $executiveSummary,
        public array $topics,
        public array $decisions,
        public array $actionItems,
        public array $risks,
        public array $pendingQuestions,
        public array $metadata = [],
    ) {}
}
