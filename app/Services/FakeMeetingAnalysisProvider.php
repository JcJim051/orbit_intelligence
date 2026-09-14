<?php

namespace App\Services;

use App\Contracts\MeetingAnalysisProvider;
use App\Data\MeetingAnalysisResult;
use App\Models\Meeting;
use App\Models\Transcript;

class FakeMeetingAnalysisProvider implements MeetingAnalysisProvider
{
    public function analyze(Meeting $meeting, Transcript $transcript): MeetingAnalysisResult
    {
        return new MeetingAnalysisResult(
            'Borrador de demostración generado para '.$meeting->title.'.',
            ['Revisión del objetivo de la reunión'],
            ['Revisar y completar el acta antes de aprobarla'],
            [[
                'description' => 'Validar el contenido del acta',
                'owner_name' => null,
                'due_date' => null,
                'due_date_text' => null,
                'source_excerpt' => null,
            ]],
            [],
            ['¿Quién será responsable del seguimiento?'],
            ['fake' => true],
        );
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-analysis';
    }
}
