<?php

namespace App\Services\Assessment;

use App\Events\Assessment\GradeReviewRequested;
use App\Events\Assessment\GradeReviewResolved;
use App\Models\Assessment\GradeReview;
use App\Models\Assessment\GradesAuditLog;
use App\Models\V1\Academic\GradeEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GradeReviewService
{
    public function createReviewRequest(array $data): GradeReview
    {
        return DB::transaction(function () use ($data) {
            $reviewData = array_merge($data, [
                'tenant_id' => session('tenant_id') ?? Auth::user()->tenant_id,
                'requester_id' => Auth::id(),
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            // Get the current grade
            $gradeEntry = GradeEntry::find($data['grade_entry_id']);
            if (!$gradeEntry) {
                throw new \Exception('Grade entry not found');
            }
            $reviewData['original_marks'] = $gradeEntry->percentage_score ?? $gradeEntry->raw_score ?? 0;

            $gradeReview = GradeReview::create($reviewData);

            event(new GradeReviewRequested($gradeReview));

            return $gradeReview->fresh(['gradeEntry', 'requester']);
        });
    }

    public function updateReviewStatus(GradeReview $gradeReview, array $data): GradeReview
    {
        return DB::transaction(function () use ($gradeReview, $data) {
            $updateData = [
                'status' => $data['status'] ?? $gradeReview->status,
                'reviewer_id' => Auth::id(),
                'reviewer_comments' => $data['reviewer_comments'] ?? null,
                'reviewed_at' => now(),
            ];

            if (isset($data['revised_marks'])) {
                $updateData['revised_marks'] = $data['revised_marks'];
            }

            if (in_array($data['status'], ['accepted', 'rejected', 'resolved'])) {
                $updateData['resolved_at'] = now();
            }

            $gradeReview->update($updateData);

            // If accepted and revised marks provided, update the grade entry
            if ($data['status'] === 'accepted' && isset($data['revised_marks'])) {
                $gradeEntry = $gradeReview->gradeEntry;
                $gradeEntry->update([
                    'percentage_score' => $data['revised_marks'],
                ]);

                // Log the change
                GradesAuditLog::create([
                    'grade_entry_id' => $gradeEntry->id,
                    'changed_by' => Auth::id(),
                    'action' => 'updated',
                    'field_name' => 'marks_awarded',
                    'old_value' => $gradeReview->original_marks,
                    'new_value' => $data['revised_marks'],
                    'reason' => 'Grade review accepted',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            event(new GradeReviewResolved($gradeReview));

            return $gradeReview->fresh();
        });
    }

    public function canRequestReview($gradeEntryId, int $userId): array
    {
        // Convert to integer if needed
        $gradeEntryId = (int) $gradeEntryId;
        
        if ($gradeEntryId <= 0) {
            return ['allowed' => false, 'reason' => 'Invalid grade entry ID'];
        }

        $gradeEntry = GradeEntry::find($gradeEntryId);
        
        if (!$gradeEntry) {
            return ['allowed' => false, 'reason' => "Grade entry with ID {$gradeEntryId} not found"];
        }

        // Check if there's already a pending or in_review review
        $existingActiveReview = GradeReview::where('grade_entry_id', $gradeEntryId)
            ->whereIn('status', ['pending', 'in_review'])
            ->first();

        if ($existingActiveReview) {
            return [
                'allowed' => false, 
                'reason' => "Já existe uma revisão {$existingActiveReview->status} para esta entrada de nota (ID: {$existingActiveReview->id}). Aguarde a resolução antes de criar uma nova revisão."
            ];
        }

        // For now, allow review requests if grade entry exists and no pending review
        return ['allowed' => true, 'reason' => null];
    }
}

