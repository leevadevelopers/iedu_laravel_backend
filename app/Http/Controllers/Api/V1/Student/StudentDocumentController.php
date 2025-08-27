<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\Student;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentDocumentController extends Controller
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of student documents with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = StudentDocument::with(['student:id,first_name,last_name,student_number', 'uploadedBy:id,name']);

            // Apply filters
            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('document_type')) {
                $query->where('document_type', $request->document_type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('expiry_date_from')) {
                $query->where('expiry_date', '>=', $request->expiry_date_from);
            }

            if ($request->has('expiry_date_to')) {
                $query->where('expiry_date', '<=', $request->expiry_date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('document_name', 'like', "%{$search}%")
                      ->orWhere('document_number', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $documents = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created student document with Form Engine processing
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'document_type' => 'required|string|max:100',
            'document_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'file_path' => 'required|string|max:500',
            'file_size' => 'required|integer|min:1',
            'file_type' => 'required|string|max:100',
            'expiry_date' => 'nullable|date|after:today',
            'status' => 'required|in:draft,pending,valid,expired,rejected',
            'priority' => 'nullable|in:low,medium,high,critical',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'form_data' => 'nullable|array', // For Form Engine integration
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create document
            $documentData = $request->except(['form_data', 'tags']);
            $documentData['uploaded_by'] = Auth::id();
            $documentData['tenant_id'] = Auth::user()->current_tenant_id;
            $documentData['tags_json'] = $request->tags ? json_encode($request->tags) : null;

            $document = StudentDocument::create($documentData);

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('document_upload', $request->form_data);
                $this->formEngineService->createFormInstance('document_upload', $processedData, 'StudentDocument', $document->id);
            }

            // Start document verification workflow
            $workflow = $this->workflowService->startWorkflow($document, 'document_verification', [
                'steps' => [
                    'initial_review',
                    'content_verification',
                    'approval',
                    'final_validation'
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'document' => $document->load(['student:id,first_name,last_name,student_number']),
                    'workflow_id' => $workflow->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified document
     */
    public function show(StudentDocument $document): JsonResponse
    {
        try {
            $document->load([
                'student:id,first_name,last_name,student_number,grade_level',
                'uploadedBy:id,name',
                'approvedBy:id,name'
            ]);

            return response()->json([
                'success' => true,
                'data' => $document
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified document
     */
    public function update(Request $request, StudentDocument $document): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_name' => 'sometimes|required|string|max:255',
            'document_number' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'expiry_date' => 'nullable|date|after:today',
            'status' => 'sometimes|required|in:draft,pending,valid,expired,rejected',
            'priority' => 'nullable|in:low,medium,high,critical',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'rejection_reason' => 'required_if:status,rejected|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->except(['tags', 'rejection_reason']);

            if ($request->has('tags')) {
                $updateData['tags_json'] = json_encode($request->tags);
            }

            if ($request->status === 'rejected') {
                $updateData['rejection_reason'] = $request->rejection_reason;
                $updateData['rejected_by'] = Auth::id();
                $updateData['rejected_at'] = now();
            }

            if ($request->status === 'valid') {
                $updateData['approved_by'] = Auth::id();
                $updateData['approved_at'] = now();
            }

            $document->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully',
                'data' => $document->fresh()->load(['student:id,first_name,last_name,student_number'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified document
     */
    public function destroy(StudentDocument $document): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Delete physical file if exists
            if ($document->file_path && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            // Soft delete document
            $document->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload document file
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'document_type' => 'required|string|max:100',
            'student_id' => 'required|exists:students,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $student = Student::findOrFail($request->student_id);

            // Generate file path
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = "students/{$student->id}/documents/{$fileName}";

            // Store file
            $file->storeAs("students/{$student->id}/documents", $fileName, 'private');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'file_path' => $filePath,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                    'original_name' => $file->getClientOriginalName()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document file
     */
    public function download(StudentDocument $document): JsonResponse
    {
        try {
            if (!Storage::exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileUrl = Storage::url($document->file_path);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $fileUrl,
                    'file_name' => $document->document_name,
                    'file_size' => $document->file_size
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate download link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documents by student
     */
    public function getByStudent(int $studentId): JsonResponse
    {
        try {
            $documents = StudentDocument::where('student_id', $studentId)
                ->with(['uploadedBy:id,name', 'approvedBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documents requiring attention (expiring soon, rejected, etc.)
     */
    public function getRequiringAttention(): JsonResponse
    {
        try {
            $documents = StudentDocument::where(function($query) {
                    $query->where('status', 'rejected')
                          ->orWhere('status', 'pending')
                          ->orWhere('expiry_date', '<=', now()->addDays(30));
                })
                ->with(['student:id,first_name,last_name,student_number', 'uploadedBy:id,name'])
                ->orderBy('priority', 'desc')
                ->orderBy('expiry_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve documents requiring attention',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update document status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'exists:student_documents,id',
            'status' => 'required|in:draft,pending,valid,expired,rejected',
            'rejection_reason' => 'required_if:status,rejected|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $documents = StudentDocument::whereIn('id', $request->document_ids)->get();
            $updatedCount = 0;

            foreach ($documents as $document) {
                $updateData = ['status' => $request->status];

                if ($request->status === 'rejected') {
                    $updateData['rejection_reason'] = $request->rejection_reason;
                    $updateData['rejected_by'] = Auth::id();
                    $updateData['rejected_at'] = now();
                }

                if ($request->status === 'valid') {
                    $updateData['approved_by'] = Auth::id();
                    $updateData['approved_at'] = now();
                }

                $document->update($updateData);
                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} documents",
                'data' => [
                    'updated_count' => $updatedCount,
                    'new_status' => $request->status
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_documents' => StudentDocument::count(),
                'by_status' => StudentDocument::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get(),
                'by_type' => StudentDocument::selectRaw('document_type, COUNT(*) as count')
                    ->groupBy('document_type')
                    ->get(),
                'expiring_soon' => StudentDocument::where('expiry_date', '<=', now()->addDays(30))
                    ->where('status', 'valid')
                    ->count(),
                'recent_uploads' => StudentDocument::where('created_at', '>=', now()->subDays(7))
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
