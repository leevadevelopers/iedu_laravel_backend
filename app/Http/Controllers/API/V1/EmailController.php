<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Constants\ErrorCodes;
use App\Http\Helpers\ApiResponse;
use App\Models\User;
use App\Notifications\StudentAccountCreated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmailController extends Controller
{
    /**
     * Send welcome email with student account credentials
     */
    public function sendStudentWelcomeEmail(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'temporary_password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error(
                    'Validation failed',
                    ErrorCodes::VALIDATION_FAILED,
                    $validator->errors(),
                    422
                );
            }

            $user = User::findOrFail($request->user_id);

            // Send welcome email
            $user->notify(new StudentAccountCreated($request->temporary_password));

            Log::info('Welcome email sent to student', [
                'user_id' => $user->id,
                'email' => $user->identifier
            ]);

            return ApiResponse::success(null, 'Welcome email sent successfully');

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::error(
                'Failed to send welcome email',
                ErrorCodes::OPERATION_FAILED,
                null,
                500
            );
        }
    }

    /**
     * Resend password reset email
     */
    public function resendPasswordReset(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,identifier',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error(
                    'Validation failed',
                    ErrorCodes::VALIDATION_FAILED,
                    $validator->errors(),
                    422
                );
            }

            $user = User::where('identifier', $request->email)->firstOrFail();

            // Generate new temporary password
            $temporaryPassword = Str::random(12);
            $user->password = bcrypt($temporaryPassword);
            $user->must_change = true;
            $user->save();

            // Send password reset email
            $user->notify(new StudentAccountCreated($temporaryPassword));

            Log::info('Password reset email sent', [
                'user_id' => $user->id,
                'email' => $user->identifier
            ]);

            return ApiResponse::success(null, 'Password reset email sent successfully');

        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ApiResponse::error(
                'Failed to send password reset email',
                ErrorCodes::OPERATION_FAILED,
                null,
                500
            );
        }
    }
}
