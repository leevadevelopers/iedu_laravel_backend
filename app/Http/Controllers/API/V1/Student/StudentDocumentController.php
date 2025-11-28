<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\StudentDocument;
use App\Models\V1\SIS\Student\Student;
use App\Models\V1\SIS\School\School;
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
use Carbon\Carbon;

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
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try getCurrentSchool method first (preferred)
        if (method_exists($user, 'getCurrentSchool')) {
            $currentSchool = $user->getCurrentSchool();
            if ($currentSchool) {
                return $currentSchool->id;
            }
        }

        // Fallback to school_id attribute
        if (isset($user->school_id) && $user->school_id) {
            return $user->school_id;
        }

        // Try activeSchools relationship
        if (method_exists($user, 'activeSchools')) {
            $activeSchools = $user->activeSchools();
            if ($activeSchools && $activeSchools->count() > 0) {
                $firstSchool = $activeSchools->first();
                if ($firstSchool && isset($firstSchool->school_id)) {
                    return $firstSchool->school_id;
                }
            }
        }

        return null;
    }

    /**
     * Get the current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try tenant_id attribute first
        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        // Try getCurrentTenant method
        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        return null;
    }

    /**
     * Verify that a school_id belongs to the user's tenant
     */
    protected function verifySchoolAccess(?int $schoolId): bool
    {
        // If school_id is null, deny access
        if (!$schoolId) {
            return false;
        }

        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return false;
        }

        // Check if school belongs to user's tenant
        $school = School::where('id', $schoolId)
            ->where('tenant_id', $tenantId)
            ->exists();

        return $school;
    }

    /**
     * Verify that user has access to a document
     */
    protected function verifyDocumentAccess(StudentDocument $document): bool
    {
        // If document has no school_id (legacy document), allow access
        // Tenant scope already ensures tenant match
        if (!$document->school_id) {
            return true;
        }

        // Get user's school ID
        $userSchoolId = $this->getCurrentSchoolId();

        // If user has no school_id, deny access (document has school_id but user doesn't)
        if (!$userSchoolId) {
            Log::warning('Document access denied: Document has school_id but user has no school_id', [
                'user_id' => Auth::id(),
                'document_id' => $document->id,
                'document_school_id' => $document->school_id
            ]);
            return false;
        }

        // Check if document belongs to user's school
        if ($document->school_id == $userSchoolId) {
            return true;
        }

        // Check if user has access to document's school (same tenant)
        if ($this->verifySchoolAccess($document->school_id)) {
            return true;
        }

        // User doesn't have access to document's school
        Log::warning('Document access denied: User does not have access to document school', [
            'user_id' => Auth::id(),
            'user_school_id' => $userSchoolId,
            'document_id' => $document->id,
            'document_school_id' => $document->school_id
        ]);
        return false;
    }

    /**
     * Handle file upload and return file metadata
     * Generates unique random filename to prevent conflicts
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $studentId
     * @param string $storageDisk
     * @return array
     * @throws \Exception
     */
    protected function handleFileUpload($file, int $studentId, string $storageDisk = 'private'): array
    {
        if (!$file || !$file->isValid()) {
            throw new \Exception('Invalid file provided');
        }

        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension() ?: 'bin';

        // Generate unique random filename: timestamp + uniqid + random string
        // Format: {timestamp}_{uniqid}_{random}.{extension}
        $timestamp = time();
        $uniqid = uniqid('', true); // More entropy
        $random = bin2hex(random_bytes(4)); // 8 random hex characters
        $fileName = "{$timestamp}_{$uniqid}_{$random}.{$extension}";

        // Organize in folders: students/{student_id}/documents/{filename}
        $directory = "students/{$studentId}/documents";

        // Store file with unique name
        $storedPath = $file->storeAs($directory, $fileName, $storageDisk);

        if (!$storedPath) {
            throw new \Exception('Failed to store file');
        }

        // Extract file type from extension (max 10 chars as per migration)
        $fileType = strtoupper(substr($extension, 0, 10));

        return [
            'file_name' => $fileName,
            'file_path' => $storedPath,
            'file_type' => $fileType,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $originalName,
        ];
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

            $query = StudentDocument::with([
                'student:id,first_name,last_name,student_number',
                'uploader:id,name'
            ]);

            // Apply school_id filter
            // Always use user's school_id or verify requested school_id belongs to user's tenant
            if ($request->has('school_id')) {
                $requestedSchoolId = (int)$request->school_id;
                if ($requestedSchoolId && $this->verifySchoolAccess($requestedSchoolId)) {
                    $query->where('school_id', $requestedSchoolId);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'You do not have access to this school'
                    ], 403);
                }
            } else {
                // Auto-filter by user's school_id
                $userSchoolId = $this->getCurrentSchoolId();
                if ($userSchoolId) {
                    $query->where('school_id', $userSchoolId);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is not associated with any school'
                    ], 403);
                }
            }

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

            // tenant_id is automatically filtered by Tenantable trait

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
     * Store a newly created student document with unified upload and creation
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id and school_id from authenticated user
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 422);
        }

        $schoolId = $this->getCurrentSchoolId();
        if (!$schoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

        // Minimal validation - only essential fields
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer|exists:students,id',
            'document_type' => 'required|in:' . implode(',', DocumentType::values()),
            'file' => 'required|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png,gif,bmp,txt,csv,xls,xlsx',
            'document_name' => 'nullable|string|max:255',
            'document_category' => 'nullable|string|max:100',
            'expiration_date' => 'nullable|date',
            'required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $studentId = $request->input('student_id');

        // Verify student belongs to the school
        $student = Student::find($studentId);
        if (!$student || $student->school_id != $schoolId) {
            return response()->json([
                'success' => false,
                'message' => 'Student does not belong to your school'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Handle file upload - generates unique random filename automatically
            $file = $request->file('file');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file provided or file upload failed'
                ], 422);
            }

            try {
                $fileMetadata = $this->handleFileUpload($file, $studentId, 'private');
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Generate document_name: use provided name, or original file name without extension
            $documentName = $request->input('document_name');
            if (!$documentName && isset($fileMetadata['original_name'])) {
                $documentName = pathinfo($fileMetadata['original_name'], PATHINFO_FILENAME);
            } elseif (!$documentName) {
                // Fallback: use document_type as name
                $documentName = ucfirst(str_replace('_', ' ', $request->document_type));
            }

            // Prepare document data with defaults
            $documentData = array_merge([
                'tenant_id' => $tenantId,
                'school_id' => $schoolId,
                'student_id' => $studentId,
                'document_name' => $documentName,
                'document_type' => $request->document_type,
                'document_category' => $request->input('document_category'),
                'status' => 'pending', // Default status
                'expiration_date' => $request->input('expiration_date'),
                'required' => $request->input('required', false),
                'verified' => false, // Default not verified
                'verification_notes' => null,
                'access_permissions_json' => null,
                'ferpa_protected' => true, // Default protected
                'uploaded_by' => Auth::id(),
            ], $fileMetadata);

            $document = StudentDocument::create($documentData);

            // Process form data through Form Engine if provided
            if ($request->has('form_data') && !empty($request->form_data)) {
                try {
                    $processedData = $this->formEngineService->processFormData('document_upload', $request->form_data);
                    $this->formEngineService->createFormInstance('document_upload', $processedData, 'StudentDocument', $document->id, $tenantId);
                } catch (\Exception $e) {
                    // Log the error but don't fail the document creation
                    Log::warning('Form Engine processing failed for document upload', [
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
                // Log workflow error but don't fail document creation
                Log::warning('Workflow creation failed for document', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage()
                ]);
                $workflow = null;
            }

            DB::commit();

            $responseData = [
                'document' => $document->load(['student:id,first_name,last_name,student_number'])
            ];

            if ($workflow) {
                $responseData['workflow_id'] = $workflow->id;
            }

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
    public function show(Request $request, $id): JsonResponse
    {
        try {


            // Find the document
            $document = StudentDocument::with([
                'student:id,first_name,last_name,student_number',
                'uploader:id,name',
                'verifier:id,name',
                'school:id,official_name,display_name'
            ])->find($id);

            // Ensure we have the document data
            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            // Verify access to document
            if (!$this->verifyDocumentAccess($document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this document'
                ], 403);
            }

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
    public function update(Request $request, StudentDocument $student_document): JsonResponse
    {
        // Capture document ID early to ensure we have it
        $documentId = $student_document->id;

        if (!$documentId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid document'
            ], 404);
        }

        // Verify access to document
        if (!$this->verifyDocumentAccess($student_document)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this document'
            ], 403);
        }

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

            // Get only allowed fields from request
            $allowedFields = [
                'document_name',
                'document_category',
                'expiration_date',
                'status',
                'required',
                'verified',
                'verification_notes',
                'access_permissions_json',
                'ferpa_protected',
            ];

            // Only include fields that are present in the request
            $updateData = [];
            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if ($request->has('status')) {
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
            }

            // Convert expiration_date from ISO 8601 to MySQL format if present
            if (isset($updateData['expiration_date']) && $updateData['expiration_date']) {
                try {
                    $date = Carbon::parse($updateData['expiration_date']);
                    $updateData['expiration_date'] = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid expiration_date format',
                        'error' => $e->getMessage()
                    ], 422);
                }
            }

            // Update document with filtered data (only if there's data to update)
            if (empty($updateData)) {
                DB::commit();
                return response()->json([
                    'success' => false,
                    'message' => 'No data provided for update'
                ], 422);
            }

            // Update using query builder to ensure it works with TenantScope
            $updated = StudentDocument::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->where('id', $documentId)
                ->update($updateData);

            Log::info('Document update executed', [
                'document_id' => $documentId,
                'update_data' => $updateData,
                'updated' => $updated
            ]);

            if ($updated === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update document or document not found'
                ], 500);
            }

            DB::commit();

            // Reload document without TenantScope to ensure we get the updated data
            $document = StudentDocument::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->with(['student:id,first_name,last_name,student_number'])
                ->find($documentId);

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found after update'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully',
                'data' => $document
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
    public function destroy(StudentDocument $student_document): JsonResponse
    {
        try {
            // Capture document ID and file path early
            $documentId = $student_document->id;
            $filePath = $student_document->file_path;

            if (!$documentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid document'
                ], 404);
            }

            // Verify access to document
            if (!$this->verifyDocumentAccess($student_document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this document'
                ], 403);
            }

            DB::beginTransaction();

            // Delete physical file if exists
            if ($filePath && Storage::exists($filePath)) {
                Storage::delete($filePath);
            }

            // Soft delete document - use withoutGlobalScope to ensure deletion works
            $deleted = StudentDocument::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                ->where('id', $documentId)
                ->delete();

            Log::info('Document delete executed', [
                'document_id' => $documentId,
                'deleted' => $deleted
            ]);

            if ($deleted === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete document or document not found'
                ], 500);
            }

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
     * Download document file
     */
    public function download(StudentDocument $document): JsonResponse
    {
        try {
            // Verify access to document
            if (!$this->verifyDocumentAccess($document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this document'
                ], 403);
            }

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
            // Verify student belongs to user's school
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any school'
                ], 403);
            }

            $student = Student::find($studentId);
            if (!$student || $student->school_id != $userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student does not belong to your school'
                ], 403);
            }

            $documents = StudentDocument::where('student_id', $studentId)
                ->where('school_id', $userSchoolId)
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
            $userSchoolId = $this->getCurrentSchoolId();
            if (!$userSchoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any school'
                ], 403);
            }

            $documents = StudentDocument::where('school_id', $userSchoolId)
                ->where(function($query) {
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
        $userSchoolId = $this->getCurrentSchoolId();
        if (!$userSchoolId) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with any school'
            ], 403);
        }

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

            // Filter documents by user's school
            $documents = StudentDocument::whereIn('id', $request->document_ids)
                ->where('school_id', $userSchoolId)
                ->get();

            if ($documents->count() !== count($request->document_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some documents do not belong to your school'
                ], 403);
            }
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
