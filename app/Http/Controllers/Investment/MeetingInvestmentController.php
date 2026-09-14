<?php

namespace App\Http\Controllers\Investment;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMeetingInvestmentRequest;
use App\Http\Requests\StoreProjectActionItemRequest;
use App\Models\InvestmentProject;
use App\Models\Meeting;
use App\Models\ProjectDecision;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MeetingInvestmentController extends Controller
{
    public function store(StoreMeetingInvestmentRequest $request, InvestmentProject $investmentProject, AuditLogger $audit): RedirectResponse
    {
        $meeting = Meeting::findOrFail($request->string('meeting_id')->toString());
        $questions = collect(preg_split('/\R/', (string) $request->validated('prepared_questions')))
            ->map(fn (string $question): string => trim($question))->filter()->values()->all();

        $meeting->investmentProjects()->syncWithoutDetaching([
            $investmentProject->id => [
                'id' => (string) Str::ulid(),
                'added_by' => $request->user()->id,
                'agenda_reason' => $request->validated('agenda_reason'),
                'prepared_questions' => json_encode($questions, JSON_UNESCAPED_UNICODE),
            ],
        ]);
        $audit->log($meeting, 'investment_project.added_to_agenda', $request->user(), ['bpin' => $investmentProject->bpin]);

        return back()->with('status', 'Proyecto agregado a la agenda de la reunión.');
    }

    public function destroy(Request $request, InvestmentProject $investmentProject, Meeting $meeting, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $meeting);
        $meeting->investmentProjects()->detach($investmentProject->id);
        $audit->log($meeting, 'investment_project.removed_from_agenda', $request->user(), ['bpin' => $investmentProject->bpin]);

        return back()->with('status', 'Proyecto retirado de la agenda.');
    }

    public function storeAction(StoreProjectActionItemRequest $request, InvestmentProject $investmentProject, AuditLogger $audit): RedirectResponse
    {
        $meeting = Meeting::findOrFail($request->string('meeting_id')->toString());
        abort_unless($meeting->investmentProjects()->whereKey($investmentProject->id)->exists(), 422, 'Primero agregue el proyecto a la agenda.');
        $summary = $meeting->currentSummary();
        abort_if($summary === null || $summary->status === 'approved', 422, 'La reunión necesita un borrador de acta editable.');
        $item = $summary->actionItems()->create([
            'investment_project_id' => $investmentProject->id,
            'description' => $request->validated('description'),
            'owner_name' => $request->validated('owner_name'),
            'due_date' => $request->validated('due_date'),
            'status' => 'draft',
        ]);
        $audit->log($meeting, 'investment_project.action_created', $request->user(), ['bpin' => $investmentProject->bpin, 'action_item_id' => $item->id]);

        return back()->with('status', 'Compromiso creado como borrador; requiere clasificación y aprobación humana.');
    }

    public function storeDecision(Request $request, InvestmentProject $investmentProject, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate(['meeting_id' => ['required', 'string', 'exists:meetings,id'], 'decision' => ['required', 'string', 'max:5000']]);
        $meeting = Meeting::findOrFail($validated['meeting_id']);
        $this->authorize('update', $meeting);
        abort_unless($meeting->investmentProjects()->whereKey($investmentProject->id)->exists(), 422, 'Primero agregue el proyecto a la agenda.');
        $summary = $meeting->currentSummary();
        abort_if($summary === null || $summary->status === 'approved', 422, 'La reunión necesita un borrador de acta editable.');
        ProjectDecision::create(['investment_project_id' => $investmentProject->id, 'meeting_summary_id' => $summary->id, 'decision' => $validated['decision']]);
        $audit->log($meeting, 'investment_project.decision_created', $request->user(), ['bpin' => $investmentProject->bpin]);

        return back()->with('status', 'Decisión asociada al proyecto y al borrador de acta.');
    }
}
