<?php

namespace App\Http\Controllers\API\V1\Communication;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Communication\SendBulkSMSRequest;
use App\Http\Requests\Communication\SendSMSRequest;
use App\Http\Resources\Communication\SMSLogResource;
use App\Models\Communication\SMSLog;
use App\Services\SMS\SMSService;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends BaseController
{
    protected SMSService $smsService;
    protected SchoolContextService $schoolContextService;

    public function __construct(SMSService $smsService, SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->smsService = $smsService;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Send a single SMS
     */
    public function sendSMS(SendSMSRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $smsLog = $this->smsService->send(
                $request->recipient,
                $request->message,
                $request->template_id,
                $schoolId
            );

            return $this->successResponse(
                new SMSLogResource($smsLog->load('sender')),
                'SMS sent successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to send SMS: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send bulk SMS
     */
    public function sendBulkSMS(SendBulkSMSRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $result = $this->smsService->sendBulk(
                $request->recipients,
                $request->message,
                $request->template_id,
                $schoolId
            );

            return $this->successResponse([
                'total' => $result['total'],
                'success' => $result['success'],
                'failed' => $result['failed'],
                'logs' => SMSLogResource::collection($result['logs']),
            ], 'Bulk SMS sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to send bulk SMS: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get SMS history
     */
    public function getSMSHistory(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();
            $status = $request->get('status');
            $limit = $request->get('limit', 50);

            $history = $this->smsService->getHistory($schoolId, $status, $limit);

            return $this->successResponse(
                SMSLogResource::collection($history),
                'SMS history retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve SMS history: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get SMS balance
     */
    public function getSMSBalance(): JsonResponse
    {
        try {
            $balance = $this->smsService->getBalance();

            return $this->successResponse([
                'balance' => $balance,
                'currency' => config('services.sms.currency', 'MZN'),
            ], 'SMS balance retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve SMS balance: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}

