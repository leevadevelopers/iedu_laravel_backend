<?php

namespace App\Services\Assessment;

use App\Events\Assessment\GradeReviewRequested;
use App\Events\Assessment\GradeReviewResolved;
use App\Models\Assessment\GradeReview;
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
            $gradeEntry = \App\Models\Assessment\GradeEntry::find($data['grade_entry_id']);
            $reviewData['original_marks'] = $gradeEntry->marks_awarded;

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
                    'marks_awarded' => $data['revised_marks'],
                ]);

                // Log the change
                \App\Models\Assessment\GradesAuditLog::create([
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

    public function canRequestReview(int $gradeEntryId, int $userId): bool
    {
        $gradeEntry = \App\Models\Assessment\GradeEntry::find($gradeEntryId);
        
        if (!$gradeEntry || !$gradeEntry->is_published) {
            return false;
        }

        // Check if there's already a pending review
        $existingReview = GradeReview::where('grade_entry_id', $gradeEntryId)
            ->where('status', 'pending')
            ->exists();

        if ($existingReview) {
            return false;
        }

        // Check deadline (if configured)
        $settings = \App\Models\Assessment\AssessmentSettings::where('tenant_id', $gradeEntry->tenant_id)
            ->where('academic_term_id', $gradeEntry->assessment->term->academic_term_id)
            ->first();

        if ($settings && $settings->review_deadline_days) {
            $deadline = $gradeEntry->published_at->addDays($settings->review_deadline_days);
            if (now()->greaterThan($deadline)) {
                return false;
            }
        }

        return true;
    }
}

