<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreCollectionRequest;
use App\Http\Requests\Library\UpdateCollectionRequest;
use App\Http\Resources\Library\CollectionResource;
use App\Models\V1\Library\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(Collection::class, 'collection');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Collection::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $collections = $query->withCount('books')->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            CollectionResource::collection($collections),
            'Collections retrieved successfully'
        );
    }

    /**
     * Summary of store
     * @param \App\Http\Requests\Library\StoreCollectionRequest $request
     * @return JsonResponse
     */
    public function store(StoreCollectionRequest $request): JsonResponse
    {
        // tenant_id is automatically set by Tenantable trait
        $collection = Collection::create($request->validated());

        return $this->successResponse(
            new CollectionResource($collection),
            'Collection created successfully',
            201
        );
    }

    public function show(Collection $collection): JsonResponse
    {
        $collection->loadCount('books');

        return $this->successResponse(
            new CollectionResource($collection),
            'Collection retrieved successfully'
        );
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): JsonResponse
    {
        $collection->update($request->validated());

        return $this->successResponse(
            new CollectionResource($collection),
            'Collection updated successfully'
        );
    }

    public function destroy(Collection $collection): JsonResponse
    {
        // Check if collection has books
        if ($collection->books()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete collection with books. Please remove all books first.',
                422
            );
        }

        $collection->delete();

        return $this->successResponse(
            null,
            'Collection deleted successfully'
        );
    }
}
