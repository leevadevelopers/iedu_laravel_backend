<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreBookRequest;
use App\Http\Requests\Library\UpdateBookRequest;
use App\Http\Resources\Library\BookResource;
use App\Models\V1\Library\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // Note: authorizeResource is enabled but we also have manual checks in methods
        $this->authorizeResource(Book::class, 'book');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Book::with(['authors', 'publisher', 'collection', 'copies']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('isbn', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        // Filter by collection
        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->collection_id);
        }

        // Filter by language
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        // Filter by visibility
        if ($request->filled('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $books = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            BookResource::collection($books),
            'Books retrieved successfully'
        );
    }

    public function store(StoreBookRequest $request): JsonResponse
    {

        $user = auth()->user();

        $tenant_id = $user->tenant_id;
        $data = array_merge($request->validated(), [
            'tenant_id' => $tenant_id,
        ]);

        $book = Book::create($data);

        if ($request->has('author_ids')) {
            $book->authors()->sync($request->author_ids);
        }

        $book->load(['authors', 'publisher', 'collection']);

        return $this->successResponse(
            new BookResource($book),
            'Book created successfully',
            201
        );
    }

    public function show(Book $book): JsonResponse
    {
        $book->load(['authors', 'publisher', 'collection', 'copies', 'files']);

        return $this->successResponse(
            new BookResource($book),
            'Book retrieved successfully'
        );
    }

    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $user = auth()->user();

        // Get user tenant_id from session or user model
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        // Ensure user can only update books from their tenant
        if ($book->tenant_id !== $userTenantId) {
            return $this->errorResponse(
                'You can only update books from your tenant.',
                403
            );
        }

        $tenant_id = $user->tenant_id;
        $data = array_merge($request->validated(), [
            'tenant_id' => $tenant_id,
        ]);

        $book->update($data);

        if ($request->has('author_ids')) {
            $book->authors()->sync($request->author_ids);
        }

        $book->load(['authors', 'publisher', 'collection']);

        return $this->successResponse(
            new BookResource($book),
            'Book updated successfully'
        );
    }

    public function destroy(Book $book): JsonResponse
    {
        $user = auth()->user();

        // Get user tenant_id from session or user model
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        // Ensure user can only delete books from their tenant
        if ($book->tenant_id !== $userTenantId) {
            return $this->errorResponse(
                'You can only delete books from your tenant.',
                403
            );
        }

        $book->delete();

        return $this->successResponse(
            null,
            'Book deleted successfully'
        );
    }
}
