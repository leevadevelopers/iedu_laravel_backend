<?php

namespace App\Http\Controllers\API\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\Student;
use App\Models\User;
use App\Models\Forms\FormTemplate;
use App\Services\FormEngineService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FamilyRelationshipController extends Controller
{
    protected $formEngineService;
    protected $workflowService;

    public function __construct(FormEngineService $formEngineService, WorkflowService $workflowService)
    {
        $this->formEngineService = $formEngineService;
        $this->workflowService = $workflowService;
    }

    /**
     * Display a listing of family relationships with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FamilyRelationship::with([
                'student:id,first_name,last_name,student_number,grade_level',
                'relatedPerson:id,first_name,last_name,email,phone',
                'createdBy:id,name'
            ]);

            // Apply filters
            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('relationship_type')) {
                $query->where('relationship_type', $request->relationship_type);
            }

            if ($request->has('is_primary_contact')) {
                $query->where('is_primary_contact', $request->boolean('is_primary_contact'));
            }

            if ($request->has('is_emergency_contact')) {
                $query->where('is_emergency_contact', $request->boolean('is_emergency_contact'));
            }

            if ($request->has('is_authorized_pickup')) {
                $query->where('is_authorized_pickup', $request->boolean('is_authorized_pickup'));
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('relatedPerson', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $relationships = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $relationships
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve family relationships',
                'error' => $e->getMessage()
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
            'related_person_id' => 'required|exists:users,id',
            'relationship_type' => 'required|in:father,mother,guardian,sibling,grandparent,uncle,aunt,other',
            'relationship_description' => 'nullable|string|max:255',
            'is_primary_contact' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'is_authorized_pickup' => 'boolean',
            'is_legal_guardian' => 'boolean',
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

            // Check if relationship already exists
            $existingRelationship = FamilyRelationship::where('student_id', $request->student_id)
                ->where('related_person_id', $request->related_person_id)
                ->first();

            if ($existingRelationship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Family relationship already exists for this person'
                ], 422);
            }

            // Create family relationship
            $relationshipData = $request->except(['form_data', 'communication_preferences']);
            $relationshipData['created_by'] = Auth::id();
            $relationshipData['tenant_id'] = Auth::user()->current_tenant_id;
            $relationshipData['communication_preferences_json'] = $request->communication_preferences ? json_encode($request->communication_preferences) : null;

            $relationship = FamilyRelationship::create($relationshipData);

            // Process form data through Form Engine if provided
            if ($request->has('form_data')) {
                $processedData = $this->formEngineService->processFormData('family_relationship', $request->form_data);
                $this->formEngineService->createFormInstance('family_relationship', $processedData, 'FamilyRelationship', $relationship->id);
            }

            // Start relationship verification workflow
            $workflow = $this->workflowService->startWorkflow($relationship, 'relationship_verification', [
                'steps' => [
                    'identity_verification',
                    'relationship_proof',
                    'background_check',
                    'final_approval'
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Family relationship created successfully',
                'data' => [
                    'relationship' => $relationship->load(['student', 'relatedPerson']),
                    'workflow_id' => $workflow->id
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create family relationship',
                'error' => $e->getMessage()
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
                'student:id,first_name,last_name,student_number,grade_level',
                'relatedPerson:id,first_name,last_name,email,phone,address',
                'createdBy:id,name'
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
            'is_emergency_contact' => 'boolean',
            'is_authorized_pickup' => 'boolean',
            'is_legal_guardian' => 'boolean',
            'contact_priority' => 'nullable|integer|min:1|max:5',
            'communication_preferences' => 'nullable|array',
            'communication_preferences.*' => 'in:email,phone,sms,mail,in_person',
            'notes' => 'nullable|string|max:1000',
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

            $updateData = $request->except(['communication_preferences']);

            if ($request->has('communication_preferences')) {
                $updateData['communication_preferences_json'] = json_encode($request->communication_preferences);
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
                ->with(['relatedPerson:id,first_name,last_name,email,phone', 'createdBy:id,name'])
                ->orderBy('is_primary_contact', 'desc')
                ->orderBy('contact_priority', 'desc')
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
                ->where('is_primary_contact', true)
                ->with(['relatedPerson:id,first_name,last_name,email,phone,address'])
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
                ->where('is_emergency_contact', true)
                ->with(['relatedPerson:id,first_name,last_name,email,phone,address'])
                ->orderBy('contact_priority', 'desc')
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
                ->where('is_authorized_pickup', true)
                ->with(['relatedPerson:id,first_name,last_name,email,phone,address'])
                ->orderBy('contact_priority', 'desc')
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
                ->where('is_primary_contact', true)
                ->update(['is_primary_contact' => false]);

            // Set new primary contact
            $relationship = FamilyRelationship::findOrFail($request->relationship_id);
            $relationship->update(['is_primary_contact' => true]);

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
            'relationships.*.related_person_id' => 'required|exists:users,id',
            'relationships.*.relationship_type' => 'required|in:father,mother,guardian,sibling,grandparent,uncle,aunt,other',
            'relationships.*.is_primary_contact' => 'boolean',
            'relationships.*.is_emergency_contact' => 'boolean',
            'relationships.*.is_authorized_pickup' => 'boolean',
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

            foreach ($request->relationships as $relationshipData) {
                // Check if relationship already exists
                $existingRelationship = FamilyRelationship::where('student_id', $request->student_id)
                    ->where('related_person_id', $relationshipData['related_person_id'])
                    ->first();

                if (!$existingRelationship) {
                    $relationship = FamilyRelationship::create([
                        'student_id' => $request->student_id,
                        'related_person_id' => $relationshipData['related_person_id'],
                        'relationship_type' => $relationshipData['relationship_type'],
                        'is_primary_contact' => $relationshipData['is_primary_contact'] ?? false,
                        'is_emergency_contact' => $relationshipData['is_emergency_contact'] ?? false,
                        'is_authorized_pickup' => $relationshipData['is_authorized_pickup'] ?? false,
                        'created_by' => Auth::id(),
                        'tenant_id' => Auth::user()->current_tenant_id
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
                'primary_contacts' => FamilyRelationship::where('is_primary_contact', true)->count(),
                'emergency_contacts' => FamilyRelationship::where('is_emergency_contact', true)->count(),
                'authorized_pickup' => FamilyRelationship::where('is_authorized_pickup', true)->count(),
                'legal_guardians' => FamilyRelationship::where('is_legal_guardian', true)->count(),
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
            $query = User::select('id', 'first_name', 'last_name', 'email', 'phone')
                ->where(function($q) use ($request) {
                    $q->where('first_name', 'like', "%{$request->search}%")
                      ->orWhere('last_name', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%");
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
