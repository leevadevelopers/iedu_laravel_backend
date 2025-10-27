<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Enums\DocumentType;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

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
            Log::info('Retrieving documents list', [
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);

            $query = StudentDocument::withoutGlobalScopes()->with([
                'student:id,first_name,last_name,student_number',
                'uploader:id,name'
            ]);

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
                $query->where('expiration_date', '>=', $request->expiry_date_from);
            }

            if ($request->has('expiry_date_to')) {
                $query->where('expiration_date', '<=', $request->expiry_date_to);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('document_name', 'like', "%{$search}%")
                      ->orWhere('file_name', 'like', "%{$search}%")
                      ->orWhere('verification_notes', 'like', "%{$search}%");
                });
            }

            $documents = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            Log::info('Documents retrieved', [
                'total_count' => $documents->total(),
                'current_page' => $documents->currentPage(),
                'per_page' => $documents->perPage()
            ]);

            return response()->json([
                'success' => true,
                'data' => $documents->items(),
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                    'last_page' => $documents->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
            'tenant_id' => 'required|exists:tenants,id',
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'required|exists:students,id',
            'document_type' => 'required|in:' . implode(',', DocumentType::values()),
            'document_name' => 'required|string|max:255',
            'document_category' => 'nullable|string|max:100',
            'file_name' => 'required|string|max:255',
            'file_path' => 'required|string|max:500',
            'file_type' => 'required|string|max:10',
            'file_size' => 'required|integer|min:1',
            'mime_type' => 'required|string|max:100',
            'expiration_date' => 'nullable|date|after:today',
            'status' => 'required|in:pending,approved,rejected,expired',
            'required' => 'nullable|boolean',
            'verified' => 'nullable|boolean',
            'verification_notes' => 'nullable|string|max:1000',
            'access_permissions_json' => 'nullable|array',
            'ferpa_protected' => 'nullable|boolean',
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
            $documentData = $request->except(['form_data']);
            $documentData['uploaded_by'] = Auth::id();

            $document = StudentDocument::create($documentData);

            // Process form data through Form Engine if provided
            if ($request->has('form_data') && !empty($request->form_data)) {
                try {
                    $processedData = $this->formEngineService->processFormData('document_upload', $request->form_data);

                    // Get tenant_id from authenticated user or request
                    $tenantId = $request->tenant_id ?? Auth::user()->tenant_id ?? Auth::user()->current_tenant_id;

                    if (!$tenantId) {
                        throw new \Exception('Tenant ID is required for form instance creation');
                    }

                    $this->formEngineService->createFormInstance('document_upload', $processedData, 'StudentDocument', $document->id, $tenantId);
                } catch (\Exception $e) {
                    // Log the error but don't fail the document creation
                    Log::warning('Form Engine processing failed for document upload', [
                        'document_id' => $document->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Start document verification workflow using createWorkflow method
            $workflow = $this->workflowService->createWorkflow([
                'workflow_type' => 'document_verification',
                'steps' => [
                    [
                        'step_number' => 1,
                        'step_name' => 'Initial Review',
                        'step_type' => 'review',
                        'required_role' => 'teacher',
                        'instructions' => 'Review document for completeness and basic requirements'
                    ],
                    [
                        'step_number' => 2,
                        'step_name' => 'Content Verification',
                        'step_type' => 'verification',
                        'required_role' => 'academic_coordinator',
                        'instructions' => 'Verify document content and authenticity'
                    ],
                    [
                        'step_number' => 3,
                        'step_name' => 'Approval',
                        'step_type' => 'approval',
                        'required_role' => 'principal',
                        'instructions' => 'Final approval of the document'
                    ],
                    [
                        'step_number' => 4,
                        'step_name' => 'Final Validation',
                        'step_type' => 'validation',
                        'required_role' => 'school_admin',
                        'instructions' => 'Final validation and archival'
                    ]
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
     * Upload file and create document in a single transaction
     */
    public function uploadAndCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'tenant_id' => 'required|exists:tenants,id',
            'school_id' => 'required|exists:schools,id',
            'student_id' => 'required|exists:students,id',
            'document_type' => 'required|in:' . implode(',', DocumentType::values()),
            'document_name' => 'required|string|max:255',
            'document_category' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date|after:today',
            'status' => 'nullable|in:pending,approved,rejected,expired',
            'required' => 'nullable|boolean',
            'verified' => 'nullable|boolean',
            'verification_notes' => 'nullable|string|max:1000',
            'access_permissions_json' => 'nullable|array',
            'ferpa_protected' => 'nullable|boolean',
            'form_data' => 'nullable|array',
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

            // Get file and student
            $file = $request->file('file');
            $student = Student::findOrFail($request->student_id);

            // Generate file path and upload
            $fileName = time() . '_' . $file->getClientOriginalName();
            $directory = "students/{$student->id}/documents";
            $storedPath = $file->storeAs($directory, $fileName, 'private');

            Log::info('File uploaded successfully', [
                'stored_path' => $storedPath,
                'student_id' => $student->id,
                'file_size' => $file->getSize()
            ]);

            // Verify file was stored
            if (!Storage::disk('private')->exists($storedPath)) {
                throw new \Exception('File was not stored successfully');
            }

            // Prepare document data
            $documentData = [
                'tenant_id' => $request->tenant_id,
                'school_id' => $request->school_id,
                'student_id' => $request->student_id,
                'document_type' => $request->document_type,
                'document_name' => $request->document_name,
                'document_category' => $request->document_category,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath, // Automatically set from upload
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'expiration_date' => $request->expiration_date,
                'status' => $request->status ?? 'pending',
                'required' => $request->required ?? false,
                'verified' => $request->verified ?? false,
                'verification_notes' => $request->verification_notes,
                'access_permissions_json' => $request->access_permissions_json,
                'ferpa_protected' => $request->ferpa_protected ?? false,
                'uploaded_by' => Auth::id(),
            ];

            // Create document
            $document = StudentDocument::create($documentData);

            Log::info('Document created successfully', [
                'document_id' => $document->id,
                'file_path' => $document->file_path
            ]);

            // Process form data through Form Engine if provided
            if ($request->has('form_data') && !empty($request->form_data)) {
                try {
                    $processedData = $this->formEngineService->processFormData('document_upload', $request->form_data);
                    $this->formEngineService->createFormInstance('document_upload', $processedData, 'StudentDocument', $document->id, $request->tenant_id);
                } catch (\Exception $e) {
                    Log::warning('Form Engine processing failed', [
                        'document_id' => $document->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Start document verification workflow
            try {
                $workflow = $this->workflowService->createWorkflow([
                    'workflow_type' => 'document_verification',
                    'steps' => [
                        [
                            'step_number' => 1,
                            'step_name' => 'Initial Review',
                            'step_type' => 'review',
                            'required_role' => 'teacher',
                            'instructions' => 'Review document for completeness and basic requirements'
                        ],
                        [
                            'step_number' => 2,
                            'step_name' => 'Content Verification',
                            'step_type' => 'verification',
                            'required_role' => 'academic_coordinator',
                            'instructions' => 'Verify document content and authenticity'
                        ],
                        [
                            'step_number' => 3,
                            'step_name' => 'Approval',
                            'step_type' => 'approval',
                            'required_role' => 'principal',
                            'instructions' => 'Final approval of the document'
                        ],
                        [
                            'step_number' => 4,
                            'step_name' => 'Final Validation',
                            'step_type' => 'validation',
                            'required_role' => 'school_admin',
                            'instructions' => 'Final validation and archival'
                        ]
                    ]
                ]);
            } catch (\Exception $e) {
                Log::warning('Workflow creation failed', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded and created successfully',
                'data' => [
                    'document' => $document->load(['student:id,first_name,last_name,student_number']),
                    'file_info' => [
                        'file_path' => $storedPath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType()
                    ],
                    'workflow_id' => $workflow->id ?? null
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded file if it exists
            if (isset($storedPath) && Storage::disk('private')->exists($storedPath)) {
                Storage::disk('private')->delete($storedPath);
                Log::info('Cleaned up uploaded file after error', [
                    'file_path' => $storedPath
                ]);
            }

            Log::error('Failed to upload and create document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload and create document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified document
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Debug: Log the request to understand what's happening
            Log::info('Retrieving document', [
                'document_id' => $id,
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            // Find the document directly by ID, bypassing any potential scopes
            $document = StudentDocument::withoutGlobalScopes()->with([
                'student:id,first_name,last_name,student_number',
                'uploader:id,name',
                'verifier:id,name',
                'school:id,official_name,display_name'
            ])->find($id);

            // Ensure we have the document data
            if (!$document) {
                Log::warning('Document not found', ['document_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            Log::info('Document found', [
                'document_id' => $document->id,
                'document_name' => $document->document_name,
                'student_id' => $document->student_id
            ]);

            // Return the document with all its data
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $document->id,
                    'school_id' => $document->school_id,
                    'student_id' => $document->student_id,
                    'document_name' => $document->document_name,
                    'document_type' => $document->document_type,
                    'document_category' => $document->document_category,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'file_type' => $document->file_type,
                    'file_size' => $document->file_size,
                    'mime_type' => $document->mime_type,
                    'status' => $document->status,
                    'expiration_date' => $document->expiration_date,
                    'required' => $document->required,
                    'verified' => $document->verified,
                    'uploaded_by' => $document->uploaded_by,
                    'verified_by' => $document->verified_by,
                    'verified_at' => $document->verified_at,
                    'verification_notes' => $document->verification_notes,
                    'access_permissions_json' => $document->access_permissions_json,
                    'ferpa_protected' => $document->ferpa_protected,
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                    'student' => $document->student,
                    'uploader' => $document->uploader,
                    'verifier' => $document->verifier,
                    'school' => $document->school
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve document', [
                'document_id' => $document->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
            'document_category' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date|after:today',
            'status' => 'sometimes|required|in:pending,approved,rejected,expired',
            'required' => 'nullable|boolean',
            'verified' => 'nullable|boolean',
            'verification_notes' => 'nullable|string|max:1000',
            'access_permissions_json' => 'nullable|array',
            'ferpa_protected' => 'nullable|boolean',
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

            $updateData = $request->except([]);

            if ($request->status === 'approved') {
                $updateData['verified'] = true;
                $updateData['verified_by'] = Auth::id();
                $updateData['verified_at'] = now();
            }

            if ($request->status === 'rejected') {
                $updateData['verified'] = false;
                $updateData['verified_by'] = Auth::id();
                $updateData['verified_at'] = now();
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
            if ($document->file_path && Storage::disk('private')->exists($document->file_path)) {
                Storage::disk('private')->delete($document->file_path);
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
            'document_type' => 'required|in:' . implode(',', DocumentType::values()),
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

            // Store file using the private disk
            $storedPath = $file->storeAs("students/{$student->id}/documents", $fileName, 'private');

            Log::info('File uploaded successfully', [
                'file_path' => $storedPath,
                'student_id' => $student->id,
                'file_size' => $file->getSize()
            ]);

            // Check if file was actually stored
            if (!Storage::disk('private')->exists($storedPath)) {
                throw new \Exception('File was not stored successfully');
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'file_path' => $storedPath,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                    'mime_type' => $file->getMimeType(),
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
            Log::info('Attempting to download document', [
                'document_id' => $document->id,
                'file_path' => $document->file_path
            ]);

            // Check if file exists using private disk
            if (!Storage::disk('private')->exists($document->file_path)) {
                Log::error('File not found on storage', [
                    'file_path' => $document->file_path,
                    'disk' => 'private'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                    'debug' => [
                        'file_path' => $document->file_path,
                        'exists' => false
                    ]
                ], 404);
            }

            // For private files, return the full path to download
            $filePath = storage_path('app/private/' . $document->file_path);

            Log::info('File found, returning download path', [
                'document_id' => $document->id,
                'file_path' => $filePath
            ]);

            // Return the download response instead of URL for private files
            return response()->json([
                'success' => true,
                'data' => [
                    'download_path' => $filePath,
                    'file_name' => $document->document_name,
                    'file_size' => $document->file_size,
                    'message' => 'Use download_path to retrieve the file'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate download link', [
                'document_id' => $document->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
                ->with(['uploader:id,name', 'verifier:id,name'])
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
                          ->orWhere('expiration_date', '<=', now()->addDays(30));
                })
                ->with(['student:id,first_name,last_name,student_number', 'uploader:id,name'])
                ->orderBy('required', 'desc')
                ->orderBy('expiration_date', 'asc')
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
            'status' => 'required|in:pending,approved,rejected,expired',
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

                if ($request->status === 'approved') {
                    $updateData['verified'] = true;
                    $updateData['verified_by'] = Auth::id();
                    $updateData['verified_at'] = now();
                }

                if ($request->status === 'rejected') {
                    $updateData['verified'] = false;
                    $updateData['verified_by'] = Auth::id();
                    $updateData['verified_at'] = now();
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
     * Get available document types
     */
    public function getDocumentTypes(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => DocumentType::options()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve document types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
