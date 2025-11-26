<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreAuthorRequest;
use App\Http\Requests\Library\UpdateAuthorRequest;
use App\Http\Resources\Library\AuthorResource;
use App\Models\V1\Library\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(Author::class, 'author');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Author::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('bio', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
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

        $authors = $query->withCount('books')->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            AuthorResource::collection($authors),
            'Authors retrieved successfully'
        );
    }

    public function store(StoreAuthorRequest $request): JsonResponse
    {
        $author = Author::create($request->validated());

        return $this->successResponse(
            new AuthorResource($author),
            'Author created successfully',
            201
        );
    }

    public function show(Author $author): JsonResponse
    {
        $author->load(['books' => function ($query) {
            $query->select('id', 'title', 'isbn', 'language')
                  ->with(['publisher:id,name', 'collection:id,name']);
        }])->loadCount('books');

        return $this->successResponse(
            new AuthorResource($author),
            'Author retrieved successfully'
        );
    }

    public function update(UpdateAuthorRequest $request, Author $author): JsonResponse
    {
        $author->update($request->validated());

        return $this->successResponse(
            new AuthorResource($author),
            'Author updated successfully'
        );
    }

    public function destroy(Author $author): JsonResponse
    {
        // Check if author has books
        if ($author->books()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete author with books. Please remove all books first.',
                422
            );
        }

        $author->delete();

        return $this->successResponse(
            null,
            'Author deleted successfully'
        );
    }
}
