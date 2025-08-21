<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Services\Project\ProjectService;
use App\Http\Requests\Project\CreateProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\Project\ProjectResource;
use App\Http\Resources\Project\ProjectListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $projects = $this->projectService->list($request->all());
        
        return response()->json([
            'data' => ProjectListResource::collection($projects->items()),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ]
        ]);
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->create($request->validated());
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project created successfully'
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $project = $this->projectService->findById($id);
        
        return response()->json([
            'data' => new ProjectResource($project)
        ]);
    }

    public function update(UpdateProjectRequest $request, int $id): JsonResponse
    {
        $project = $this->projectService->update($id, $request->validated());
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project updated successfully'
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->projectService->delete($id);
        
        return response()->json([
            'message' => 'Project deleted successfully'
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $project = $this->projectService->approve($id);
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project approved successfully'
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $project = $this->projectService->activate($id);
        
        return response()->json([
            'data' => new ProjectResource($project),
            'message' => 'Project activated successfully'
        ]);
    }
}
