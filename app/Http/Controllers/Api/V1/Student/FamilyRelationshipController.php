<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use App\Services\SchoolContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class FamilyRelationshipController extends Controller
{
    protected $formEngineService;
    protected $workflowService;
    protected $schoolContextService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService, SchoolContextService $schoolContextService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Display a listing of family relationships with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FamilyRelationship::with([
                'student:id,first_name,last_name,student_number,current_grade_level',
                'relatedPerson:id,name,identifier,phone'
            ]);

            // Apply filters
            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('relationship_type')) {
                $query->where('relationship_type', $request->relationship_type);
            }

            // Handle both is_* and direct field names for compatibility
            if ($request->has('is_primary_contact') || $request->has('primary_contact')) {
                $value = $request->has('is_primary_contact') 
                    ? $request->boolean('is_primary_contact')
                    : $request->boolean('primary_contact');
                $query->where('primary_contact', $value);
            }

            if ($request->has('is_emergency_contact') || $request->has('emergency_contact')) {
                $value = $request->has('is_emergency_contact')
                    ? $request->boolean('is_emergency_contact')
                    : $request->boolean('emergency_contact');
                $query->where('emergency_contact', $value);
            }

            if ($request->has('is_authorized_pickup') || $request->has('pickup_authorized')) {
                $value = $request->has('is_authorized_pickup')
                    ? $request->boolean('is_authorized_pickup')
                    : $request->boolean('pickup_authorized');
                $query->where('pickup_authorized', $value);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('relatedPerson', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('identifier', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $relationships = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $relationships
            ]);

        } catch (\Exception $e) {
            // Log the full error for debugging
            \Log::error('Failed to retrieve family relationships', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve family relationships',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while retrieving family relationships.'
            ], 500);
        }
    }

    /**
     * Store a newly created family relationship with Form Engine processing
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'related_person_id' => 'required_without:guardian_user_id|exists:users,id',
            'guardian_user_id' => 'required_without:related_person_id|exists:users,id',
            'relationship_type' => 'required|in:father,mother,guardian,sibling,grandparent,uncle,aunt,other',
            'relationship_description' => 'nullable|string|max:255',
            'is_primary_contact' => 'boolean',
            'primary_contact' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'emergency_contact' => 'boolean',
            'is_authorized_pickup' => 'boolean',
            'pickup_authorized' => 'boolean',
            'is_legal_guardian' => 'boolean',
            'custody_rights' => 'boolean',
            'contact_priority' => 'nullable|integer|min:1|max:5',
            'communication_preferences' => 'nullable|array',
            'communication_preferences.*' => 'in:email,phone,sms,mail,in_person',
            'notes' => 'nullable|string|max:1000',
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

            // Map related_person_id to guardian_user_id if needed
            $guardianUserId = $request->guardian_user_id ?? $request->related_person_id;
            
            // Check if active relationship already exists (ignore soft-deleted ones)
            $existingRelationship = FamilyRelationship::where('student_id', $request->student_id)
                ->where('guardian_user_id', $guardianUserId)
                ->whereNull('deleted_at') // Only check active relationships
                ->first();

            if ($existingRelationship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Já existe um relacionamento ativo entre este estudante e esta pessoa. Por favor, edite o relacionamento existente ou escolha outra pessoa.',
                    'existing_relationship_id' => $existingRelationship->id,
                    'data' => [
                        'existing_relationship' => $existingRelationship->load(['student', 'relatedPerson'])
                    ]
                ], 422);
            }
            
            // Check for soft-deleted relationship that could be restored instead
            $deletedRelationship = FamilyRelationship::withTrashed()
                ->where('student_id', $request->student_id)
                ->where('guardian_user_id', $guardianUserId)
                ->whereNotNull('deleted_at')
                ->first();
            
            // If there's a soft-deleted relationship, we'll create a new one (don't restore automatically)
            // The user can explicitly restore if needed

            // Create family relationship - map fields to database columns
            $relationshipData = [];
            // Get school_id from request, or fallback to session, or use SchoolContextService
            $schoolId = $request->school_id 
                ?? Session::get('current_school_id')
                ?? (Auth::user()?->getCurrentSchool()?->id)
                ?? (Auth::check() ? $this->schoolContextService->getCurrentSchoolId() : null);
            
            if (!$schoolId) {
                throw new \RuntimeException('School ID is required. No school context available.');
            }
            
            // Get tenant_id from school or context
            $tenantId = $request->tenant_id
                ?? Session::get('current_tenant_id')
                ?? (Auth::check() ? $this->schoolContextService->getCurrentTenantId() : null);
            
            // If tenant_id still not found, get it from school
            if (!$tenantId && $schoolId) {
                $school = \App\Models\V1\SIS\School\School::find($schoolId);
                if ($school && $school->tenant_id) {
                    $tenantId = $school->tenant_id;
                }
            }
            
            if (!$tenantId) {
                throw new \RuntimeException('Tenant ID is required. No tenant context available.');
            }
            
            $relationshipData['school_id'] = $schoolId;
            $relationshipData['student_id'] = $request->student_id;
            $relationshipData['guardian_user_id'] = $guardianUserId;
            $relationshipData['relationship_type'] = $request->relationship_type;
            $relationshipData['relationship_description'] = $request->relationship_description;
            
            // Map boolean fields (accept both is_* and direct field names)
            $relationshipData['primary_contact'] = $request->is_primary_contact ?? $request->primary_contact ?? false;
            $relationshipData['emergency_contact'] = $request->is_emergency_contact ?? $request->emergency_contact ?? false;
            $relationshipData['pickup_authorized'] = $request->is_authorized_pickup ?? $request->pickup_authorized ?? false;
            $relationshipData['custody_rights'] = $request->is_legal_guardian ?? $request->custody_rights ?? false;
            
            // Optional fields with defaults
            $relationshipData['academic_access'] = $request->academic_access ?? true;
            $relationshipData['medical_access'] = $request->medical_access ?? false;
            $relationshipData['financial_responsibility'] = $request->financial_responsibility ?? false;
            $relationshipData['status'] = $request->status ?? 'active';
            
            // Handle communication preferences
            if ($request->has('communication_preferences')) {
                $relationshipData['communication_preferences_json'] = json_encode($request->communication_preferences);
            }
            
            // Handle custody details if provided
            if ($request->has('custody_details_json')) {
                $relationshipData['custody_details_json'] = is_array($request->custody_details_json) 
                    ? json_encode($request->custody_details_json) 
                    : $request->custody_details_json;
            }

            $relationship = FamilyRelationship::create($relationshipData);
            
            // Load school relationship to enable tenant_id accessor
            $relationship->load('school');

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('family_relationship', $request->form_data);
                $this->formEngineService->createFormInstance('family_relationship', $processedData, 'FamilyRelationship', $relationship->id, $tenantId);
            }

            // Try to start relationship verification workflow (optional, don't fail if it doesn't work)
            $workflow = null;
            try {
                $workflow = $this->workflowService->startWorkflow($relationship, 'relationship_verification', [
                    'steps' => [
                        'identity_verification',
                        'relationship_proof',
                        'background_check',
                        'final_approval'
                    ]
                ]);
            } catch (\Exception $workflowException) {
                // Log but don't fail the relationship creation
                \Log::warning('Failed to start workflow for family relationship', [
                    'relationship_id' => $relationship->id,
                    'error' => $workflowException->getMessage()
                ]);
            }

            DB::commit();

            $responseData = [
                'relationship' => $relationship->load(['student', 'relatedPerson'])
            ];
            
            if ($workflow) {
                $responseData['workflow_id'] = $workflow->id;
            }

            return response()->json([
                'success' => true,
                'message' => 'Family relationship created successfully',
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the full error for debugging
            \Log::error('Failed to create family relationship', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create family relationship',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred while creating the family relationship. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified family relationship
     */
    public function show(FamilyRelationship $relationship): JsonResponse
    {
        try {
            $relationship->load([
                'student:id,first_name,last_name,student_number,current_grade_level',
                'relatedPerson:id,name,identifier,phone'
            ]);

            return response()->json([
                'success' => true,
                'data' => $relationship
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve family relationship',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified family relationship
     */
    public function update(Request $request, FamilyRelationship $relationship): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'relationship_type' => 'sometimes|required|in:father,mother,guardian,sibling,grandparent,uncle,aunt,other',
            'relationship_description' => 'nullable|string|max:255',
            'is_primary_contact' => 'boolean',
            'primary_contact' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'emergency_contact' => 'boolean',
            'is_authorized_pickup' => 'boolean',
            'pickup_authorized' => 'boolean',
            'is_legal_guardian' => 'boolean',
            'custody_rights' => 'boolean',
            'academic_access' => 'boolean',
            'medical_access' => 'boolean',
            'financial_responsibility' => 'boolean',
            'status' => 'sometimes|in:active,inactive,archived',
            'contact_priority' => 'nullable|integer|min:1|max:5',
            'communication_preferences' => 'nullable|array',
            'communication_preferences.*' => 'in:email,phone,sms,mail,in_person',
            'notes' => 'nullable|string|max:1000',
            'custody_details_json' => 'nullable|array',
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

            // Map fields to database columns
            $updateData = [];
            
            if ($request->has('relationship_type')) {
                $updateData['relationship_type'] = $request->relationship_type;
            }
            if ($request->has('relationship_description')) {
                $updateData['relationship_description'] = $request->relationship_description;
            }
            
            // Map boolean fields (accept both is_* and direct field names)
            if ($request->has('is_primary_contact') || $request->has('primary_contact')) {
                $updateData['primary_contact'] = $request->has('is_primary_contact') 
                    ? $request->boolean('is_primary_contact')
                    : $request->boolean('primary_contact');
            }
            if ($request->has('is_emergency_contact') || $request->has('emergency_contact')) {
                $updateData['emergency_contact'] = $request->has('is_emergency_contact')
                    ? $request->boolean('is_emergency_contact')
                    : $request->boolean('emergency_contact');
            }
            if ($request->has('is_authorized_pickup') || $request->has('pickup_authorized')) {
                $updateData['pickup_authorized'] = $request->has('is_authorized_pickup')
                    ? $request->boolean('is_authorized_pickup')
                    : $request->boolean('pickup_authorized');
            }
            if ($request->has('is_legal_guardian') || $request->has('custody_rights')) {
                $updateData['custody_rights'] = $request->has('is_legal_guardian')
                    ? $request->boolean('is_legal_guardian')
                    : $request->boolean('custody_rights');
            }
            if ($request->has('academic_access')) {
                $updateData['academic_access'] = $request->boolean('academic_access');
            }
            if ($request->has('medical_access')) {
                $updateData['medical_access'] = $request->boolean('medical_access');
            }
            if ($request->has('financial_responsibility')) {
                $updateData['financial_responsibility'] = $request->boolean('financial_responsibility');
            }
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            // Handle communication preferences
            if ($request->has('communication_preferences')) {
                $updateData['communication_preferences_json'] = json_encode($request->communication_preferences);
            }
            
            // Handle custody details
            if ($request->has('custody_details_json')) {
                $updateData['custody_details_json'] = is_array($request->custody_details_json) 
                    ? json_encode($request->custody_details_json) 
                    : $request->custody_details_json;
            }
            
            // Handle notes (store in custody_details_json.restrictions if provided)
            if ($request->has('notes')) {
                $custodyDetails = json_decode($relationship->custody_details_json ?? '{}', true);
                $custodyDetails['restrictions'] = $request->notes;
                $updateData['custody_details_json'] = json_encode($custodyDetails);
            }

            $relationship->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Family relationship updated successfully',
                'data' => $relationship->fresh()->load(['student', 'relatedPerson'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update family relationship',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified family relationship
     */
    public function destroy(FamilyRelationship $relationship): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Soft delete relationship
            $relationship->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Family relationship deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete family relationship',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get family relationships by student
     */
    public function getByStudent(int $studentId): JsonResponse
    {
        try {
            $relationships = FamilyRelationship::where('student_id', $studentId)
                ->with(['guardian:id,name,identifier,phone'])
                ->orderBy('primary_contact', 'desc')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $relationships
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve student family relationships',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get primary contact for a student
     */
    public function getPrimaryContact(int $studentId): JsonResponse
    {
        try {
            $primaryContact = FamilyRelationship::where('student_id', $studentId)
                ->where('primary_contact', true)
                ->with(['guardian:id,name,identifier,phone'])
                ->first();

            if (!$primaryContact) {
                return response()->json([
                    'success' => false,
                    'message' => 'No primary contact found for this student'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $primaryContact
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve primary contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get emergency contacts for a student
     */
    public function getEmergencyContacts(int $studentId): JsonResponse
    {
        try {
            $emergencyContacts = FamilyRelationship::where('student_id', $studentId)
                ->where('emergency_contact', true)
                ->with(['relatedPerson:id,name,identifier,phone'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $emergencyContacts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve emergency contacts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authorized pickup persons for a student
     */
    public function getAuthorizedPickupPersons(int $studentId): JsonResponse
    {
        try {
            $pickupPersons = FamilyRelationship::where('student_id', $studentId)
                ->where('pickup_authorized', true)
                ->with(['relatedPerson:id,name,identifier,phone'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pickupPersons
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve authorized pickup persons',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set primary contact for a student
     */
    public function setPrimaryContact(Request $request, int $studentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'relationship_id' => 'required|exists:family_relationships,id',
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

            // Remove existing primary contact
            FamilyRelationship::where('student_id', $studentId)
                ->where('primary_contact', true)
                ->update(['primary_contact' => false]);

            // Set new primary contact
            $relationship = FamilyRelationship::findOrFail($request->relationship_id);
            $relationship->update(['primary_contact' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Primary contact updated successfully',
                'data' => $relationship->load(['student', 'relatedPerson'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update primary contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create family relationships
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'relationships' => 'required|array|min:1',
            'relationships.*.related_person_id' => 'required_without:relationships.*.guardian_user_id|exists:users,id',
            'relationships.*.guardian_user_id' => 'required_without:relationships.*.related_person_id|exists:users,id',
            'relationships.*.relationship_type' => 'required|in:father,mother,guardian,sibling,grandparent,uncle,aunt,other',
            'relationships.*.is_primary_contact' => 'boolean',
            'relationships.*.primary_contact' => 'boolean',
            'relationships.*.is_emergency_contact' => 'boolean',
            'relationships.*.emergency_contact' => 'boolean',
            'relationships.*.is_authorized_pickup' => 'boolean',
            'relationships.*.pickup_authorized' => 'boolean',
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

            $createdCount = 0;
            $relationships = [];

            // Get school_id from request or context
            $schoolId = $request->school_id 
                ?? Session::get('current_school_id')
                ?? (Auth::user()?->getCurrentSchool()?->id)
                ?? (Auth::check() ? $this->schoolContextService->getCurrentSchoolId() : null);
            
            if (!$schoolId) {
                throw new \RuntimeException('School ID is required. No school context available.');
            }

            foreach ($request->relationships as $relationshipData) {
                // Map related_person_id to guardian_user_id if needed
                $guardianUserId = $relationshipData['guardian_user_id'] ?? $relationshipData['related_person_id'] ?? null;
                
                if (!$guardianUserId) {
                    continue; // Skip if no guardian_user_id provided
                }
                
                // Check if relationship already exists
                $existingRelationship = FamilyRelationship::where('student_id', $request->student_id)
                    ->where('guardian_user_id', $guardianUserId)
                    ->first();

                if (!$existingRelationship) {
                    $relationship = FamilyRelationship::create([
                        'school_id' => $schoolId,
                        'student_id' => $request->student_id,
                        'guardian_user_id' => $guardianUserId,
                        'relationship_type' => $relationshipData['relationship_type'],
                        'primary_contact' => $relationshipData['is_primary_contact'] ?? $relationshipData['primary_contact'] ?? false,
                        'emergency_contact' => $relationshipData['is_emergency_contact'] ?? $relationshipData['emergency_contact'] ?? false,
                        'pickup_authorized' => $relationshipData['is_authorized_pickup'] ?? $relationshipData['pickup_authorized'] ?? false,
                        'academic_access' => $relationshipData['academic_access'] ?? true,
                        'medical_access' => $relationshipData['medical_access'] ?? false,
                        'financial_responsibility' => $relationshipData['financial_responsibility'] ?? false,
                        'status' => $relationshipData['status'] ?? 'active'
                    ]);

                    $relationships[] = $relationship;
                    $createdCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$createdCount} family relationships",
                'data' => [
                    'created_count' => $createdCount,
                    'relationships' => $relationships
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create family relationships',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get family relationship statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_relationships' => FamilyRelationship::count(),
                'by_relationship_type' => FamilyRelationship::selectRaw('relationship_type, COUNT(*) as count')
                    ->groupBy('relationship_type')
                    ->get(),
                'primary_contacts' => FamilyRelationship::where('primary_contact', true)->count(),
                'emergency_contacts' => FamilyRelationship::where('emergency_contact', true)->count(),
                'authorized_pickup' => FamilyRelationship::where('pickup_authorized', true)->count(),
                'legal_guardians' => FamilyRelationship::where('custody_rights', true)->count(),
                'recent_relationships' => FamilyRelationship::where('created_at', '>=', now()->subDays(30))
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get family relationship statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for potential family members
     */
    public function searchPotentialMembers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2',
            'exclude_ids' => 'nullable|array',
            'exclude_ids.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = User::select('id', 'name', 'identifier', 'phone')
                ->where(function($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                      ->orWhere('identifier', 'like', "%{$request->search}%")
                      ->orWhere('phone', 'like', "%{$request->search}%");
                });

            if ($request->has('exclude_ids')) {
                $query->whereNotIn('id', $request->exclude_ids);
            }

            $potentialMembers = $query->limit(10)->get();

            return response()->json([
                'success' => true,
                'data' => $potentialMembers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search potential family members',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
