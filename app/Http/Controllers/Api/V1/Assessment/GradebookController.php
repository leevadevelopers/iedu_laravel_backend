<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreGradebookRequest;
use App\Http\Resources\Assessment\GradebookResource;
use App\Models\Assessment\Gradebook;
use App\Jobs\Assessment\GenerateGradebookReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GradebookController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $query = Gradebook::with(['subject', 'class', 'term', 'uploader', 'approver']);

        // Filter by subject
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by term
        if ($request->filled('term_id')) {
            $query->where('term_id', $request->term_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $gradebooks = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            GradebookResource::collection($gradebooks),
            'Gradebooks retrieved successfully'
        );
    }

    public function store(StoreGradebookRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.upload')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $tenantId = session('tenant_id') ?? auth()->user()->tenant_id;

        // Handle file upload
        $filePath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('gradebooks/' . $tenantId, $filename);
        }

        $gradebook = Gradebook::create([
            'tenant_id' => $tenantId,
            'subject_id' => $request->subject_id,
            'class_id' => $request->class_id,
            'term_id' => $request->term_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => $filePath,
            'uploaded_by' => auth()->id(),
            'uploaded_at' => now(),
        ]);

        return $this->successResponse(
            new GradebookResource($gradebook->load(['subject', 'class', 'term'])),
            'Gradebook uploaded successfully',
            201
        );
    }

    public function show(Gradebook $gradebook): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradebook->load(['subject', 'class', 'term', 'uploader', 'approver', 'files']);

        return $this->successResponse(
            new GradebookResource($gradebook),
            'Gradebook retrieved successfully'
        );
    }

    public function download(Gradebook $gradebook): mixed
    {
        // if (!auth()->user()->can('assessment.gradebooks.download')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        if (!$gradebook->file_path || !Storage::exists($gradebook->file_path)) {
            return $this->errorResponse('File not found', 404);
        }

        return Storage::download($gradebook->file_path);
    }

    public function approve(Request $request, Gradebook $gradebook): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradebook->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->successResponse(
            new GradebookResource($gradebook),
            'Gradebook approved successfully'
        );
    }

    public function reject(Request $request, Gradebook $gradebook): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradebook->update([
            'status' => 'rejected',
        ]);

        return $this->successResponse(
            new GradebookResource($gradebook),
            'Gradebook rejected successfully'
        );
    }

    public function destroy(Gradebook $gradebook): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.manage')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Delete file
        if ($gradebook->file_path && Storage::exists($gradebook->file_path)) {
            Storage::delete($gradebook->file_path);
        }

        $gradebook->delete();

        return $this->successResponse(
            null,
            'Gradebook deleted successfully'
        );
    }

    public function generate(Request $request, Gradebook $gradebook): JsonResponse
    {
        // if (!auth()->user()->can('assessment.gradebooks.download')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $request->validate([
            'format' => 'nullable|in:pdf,csv,xlsx',
        ]);

        $format = $request->get('format', 'pdf');

        GenerateGradebookReport::dispatch($gradebook, $format);

        return $this->successResponse(
            null,
            'Gradebook report generation started'
        );
    }
}

