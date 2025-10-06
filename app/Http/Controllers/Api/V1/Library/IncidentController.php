<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreIncidentRequest;
use App\Http\Resources\Library\IncidentResource;
use App\Models\V1\Library\Incident;
use App\Events\Library\IncidentReported;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class IncidentController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // $this->authorize('viewAny', Incident::class);

        $query = Incident::with(['bookCopy.book', 'loan', 'reporter', 'resolver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $incidents = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            IncidentResource::collection($incidents),
            'Incidents retrieved successfully'
        );
    }

    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Server-managed fields
        $tenant_id = Auth::user()?->tenant_id;
        $reporter_id = Auth::id();
        $status = 'reported';


        $data = array_merge($request->validated(), [
            'tenant_id' => $tenant_id,
            'reporter_id' =>$reporter_id,
            'status' => $status
        ]);


        $incident = Incident::create($data);

        event(new IncidentReported($incident));

        return $this->successResponse(
            new IncidentResource($incident->load(['bookCopy.book', 'loan', 'reporter', 'resolver'])),
            'Incident reported successfully',
            201
        );
    }

    public function show(Incident $libraryIncident): JsonResponse
    {
        return $this->successResponse(
            new IncidentResource($libraryIncident->load(['bookCopy.book', 'loan', 'reporter', 'resolver'])),
            'Incident retrieved successfully'
        );
    }

    public function resolve(Request $request, Incident $libraryIncident): JsonResponse
    {
        // $this->authorize('resolve', $libraryIncident);

        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'assessed_fine' => ['nullable', 'numeric', 'min:0'],
        ]);

        $libraryIncident->update(array_merge($validated, [
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]));

        return $this->successResponse(
            new IncidentResource($libraryIncident->load(['bookCopy.book', 'loan', 'reporter', 'resolver'])),
            'Incident resolved successfully'
        );
    }

    public function close(Incident $libraryIncident): JsonResponse
    {
        // $this->authorize('resolve', $libraryIncident);

        $libraryIncident->update([
            'status' => 'closed',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return $this->successResponse(
            new IncidentResource($libraryIncident->load(['bookCopy.book', 'loan', 'reporter', 'resolver'])),
            'Incident closed successfully'
        );
    }
}
