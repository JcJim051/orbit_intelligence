<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\MeetingExportService;

class MeetingExportController extends Controller
{
    public function markdown(Meeting $meeting, MeetingExportService $exports)
    {
        $this->authorize('view', $meeting);
        $summary = $meeting->currentSummary() ?? abort(404);

        return response($exports->markdown($meeting, $summary), 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="acta-'.$meeting->id.'.md"']);
    }

    public function pdf(Meeting $meeting, MeetingExportService $exports)
    {
        $this->authorize('view', $meeting);
        $summary = $meeting->currentSummary() ?? abort(404);

        return response($exports->pdf($meeting, $summary), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="acta-'.$meeting->id.'.pdf"']);
    }
}
