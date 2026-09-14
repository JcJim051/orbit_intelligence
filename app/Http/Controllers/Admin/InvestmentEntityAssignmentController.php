<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestmentEntity;
use App\Models\InvestmentEntityAssignment;
use App\Models\InvestmentProject;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvestmentEntityAssignmentController extends Controller
{
    public function store(Request $request, InvestmentProject $investmentProject, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'investment_entity_id' => ['required', 'exists:investment_entities,id'],
            'role' => ['required', 'in:primary,collaborator'],
        ]);

        $assignment = DB::transaction(function () use ($audit, $investmentProject, $request, $validated): InvestmentEntityAssignment {
            if ($validated['role'] === 'primary') {
                InvestmentEntityAssignment::query()
                    ->where('investment_project_id', $investmentProject->id)
                    ->where('status', 'confirmed')->where('role', 'primary')
                    ->update(['role' => 'collaborator']);
            }
            $assignment = InvestmentEntityAssignment::updateOrCreate(
                ['investment_project_id' => $investmentProject->id, 'investment_entity_id' => $validated['investment_entity_id']],
                [
                    'role' => $validated['role'], 'status' => 'confirmed', 'method' => 'manual_review', 'confidence' => 1,
                    'evidence' => ['source' => 'unclassified_queue'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                ]
            );
            $audit->log(null, 'investment_entity_assignment_created', $request->user(), [
                'project_id' => $investmentProject->id,
                'assignment_id' => $assignment->id,
            ], 'investment', [], $assignment->fresh()->load('entity')->toArray());

            return $assignment;
        });

        return back()->with('status', "Proyecto {$assignment->project->bpin} clasificado y auditado.");
    }

    public function update(Request $request, InvestmentEntityAssignment $assignment, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:confirm,reject'],
            'investment_entity_id' => ['required_if:decision,confirm', 'nullable', 'exists:investment_entities,id'],
            'role' => ['required_if:decision,confirm', 'nullable', 'in:primary,collaborator'],
        ]);
        $old = $assignment->load('entity')->toArray();

        DB::transaction(function () use ($assignment, $audit, $request, $validated, $old): void {
            if ($validated['decision'] === 'reject') {
                $assignment->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
                $target = $assignment;
            } else {
                $targetEntity = InvestmentEntity::findOrFail($validated['investment_entity_id']);
                if ($validated['role'] === 'primary') {
                    InvestmentEntityAssignment::query()
                        ->where('investment_project_id', $assignment->investment_project_id)
                        ->where('status', 'confirmed')->where('role', 'primary')
                        ->where('investment_entity_id', '!=', $targetEntity->id)
                        ->update(['role' => 'collaborator']);
                }
                if ($targetEntity->id !== $assignment->investment_entity_id) {
                    $assignment->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
                }
                $target = InvestmentEntityAssignment::updateOrCreate(
                    ['investment_project_id' => $assignment->investment_project_id, 'investment_entity_id' => $targetEntity->id],
                    [
                        'role' => $validated['role'], 'status' => 'confirmed', 'method' => 'manual_review', 'confidence' => 1,
                        'evidence' => ['reviewed_from_assignment' => $assignment->id, 'original_evidence' => $assignment->evidence],
                        'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                    ]
                );
            }

            $audit->log(null, 'investment_entity_assignment_reviewed', $request->user(), [
                'project_id' => $assignment->investment_project_id,
                'assignment_id' => $target->id,
                'decision' => $validated['decision'],
            ], 'investment', $old, $target->fresh()->load('entity')->toArray());
        });

        return back()->with('status', 'Clasificación revisada y registrada.');
    }
}
