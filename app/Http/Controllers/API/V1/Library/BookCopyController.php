<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreBookCopyRequest;
use App\Http\Requests\Library\UpdateBookCopyRequest;
use App\Http\Resources\Library\BookCopyResource;
use App\Models\V1\Library\Book;
use App\Models\V1\Library\BookCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookCopyController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request, ?Book $book = null): JsonResponse
    {
        $query = BookCopy::with(['book', 'activeLoan']);

        // Filter by book (from nested route or request parameter)
        if ($book) {
            $query->where('book_id', $book->id);
        } elseif ($request->filled('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by location
        if ($request->filled('location')) {
            $query->where('location', 'like', "%{$request->location}%");
        }

        // Search by barcode
        if ($request->filled('barcode')) {
            $query->where('barcode', 'like', "%{$request->barcode}%");
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $bookCopies = $query->withCount('loans')->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            BookCopyResource::collection($bookCopies),
            'Book copies retrieved successfully'
        );
    }

    public function store(StoreBookCopyRequest $request, ?Book $book = null): JsonResponse
    {
        // If called from nested route, use the book parameter
        if ($book) {
            $bookId = $book->id;
        } else {
            $bookId = $request->book_id;
        }

        // Verify book belongs to user's tenant
        $book = $book ?? Book::findOrFail($bookId);
        $user = auth()->user();
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        if ($book->tenant_id !== $userTenantId) {
            return $this->errorResponse(
                'You can only create copies for books from your tenant.',
                403
            );
        }

        $data = $request->validated();
        $data['book_id'] = $bookId;

        $bookCopy = BookCopy::create($data);

        $bookCopy->load(['book', 'activeLoan']);

        return $this->successResponse(
            new BookCopyResource($bookCopy),
            'Book copy created successfully',
            201
        );
    }

    public function show(BookCopy $bookCopy): JsonResponse
    {
        $bookCopy->load(['book', 'activeLoan', 'loans'])->loadCount('loans');

        return $this->successResponse(
            new BookCopyResource($bookCopy),
            'Book copy retrieved successfully'
        );
    }

    public function update(UpdateBookCopyRequest $request, BookCopy $bookCopy): JsonResponse
    {
        // Verify book copy belongs to user's tenant through the book
        $book = $bookCopy->book;
        $user = auth()->user();
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        if ($book->tenant_id !== $userTenantId) {
            return $this->errorResponse(
                'You can only update copies from books in your tenant.',
                403
            );
        }

        $bookCopy->update($request->validated());

        $bookCopy->load(['book', 'activeLoan']);

        return $this->successResponse(
            new BookCopyResource($bookCopy),
            'Book copy updated successfully'
        );
    }

    public function destroy(BookCopy $bookCopy): JsonResponse
    {
        // Verify book copy belongs to user's tenant through the book
        $book = $bookCopy->book;
        $user = auth()->user();
        $userTenantId = session('tenant_id') ?? $user->tenant_id;

        if ($book->tenant_id !== $userTenantId) {
            return $this->errorResponse(
                'You can only delete copies from books in your tenant.',
                403
            );
        }

        // Check if copy has active loans
        if ($bookCopy->activeLoan) {
            return $this->errorResponse(
                'Cannot delete book copy with active loan. Return the book first.',
                422
            );
        }

        $bookCopy->delete();

        return $this->successResponse(
            null,
            'Book copy deleted successfully'
        );
    }
}
