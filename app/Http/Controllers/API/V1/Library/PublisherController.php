<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StorePublisherRequest;
use App\Http\Requests\Library\UpdatePublisherRequest;
use App\Http\Resources\Library\PublisherResource;
use App\Models\V1\Library\Publisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublisherController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(Publisher::class, 'publisher');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Publisher::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('website', 'like', "%{$search}%");
            });
        }

        // Filter by country
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $publishers = $query->withCount('books')->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            PublisherResource::collection($publishers),
            'Publishers retrieved successfully'
        );
    }

    public function store(StorePublisherRequest $request): JsonResponse
    {
        $publisher = Publisher::create($request->validated());

        return $this->successResponse(
            new PublisherResource($publisher),
            'Publisher created successfully',
            201
        );
    }

    public function show(Publisher $publisher): JsonResponse
    {
        $publisher->load(['books' => function ($query) {
            $query->select('id', 'title', 'isbn', 'language', 'published_at')
                  ->with(['authors:id,name', 'collection:id,name']);
        }])->loadCount('books');

        return $this->successResponse(
            new PublisherResource($publisher),
            'Publisher retrieved successfully'
        );
    }

    public function update(UpdatePublisherRequest $request, Publisher $publisher): JsonResponse
    {
        $publisher->update($request->validated());

        return $this->successResponse(
            new PublisherResource($publisher),
            'Publisher updated successfully'
        );
    }

    public function destroy(Publisher $publisher): JsonResponse
    {
        // Check if publisher has books
        if ($publisher->books()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete publisher with books. Please remove all books first.',
                422
            );
        }

        $publisher->delete();

        return $this->successResponse(
            null,
            'Publisher deleted successfully'
        );
    }
}
