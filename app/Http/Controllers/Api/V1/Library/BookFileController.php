<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreBookFileRequest;
use App\Http\Requests\Library\UpdateBookFileRequest;
use App\Http\Resources\Library\BookFileResource;
use App\Models\V1\Library\BookFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookFileController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(BookFile::class, 'bookFile');
    }

    public function index(Request $request): JsonResponse
    {
        $query = BookFile::with('book');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('book', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        // Filter by book
        if ($request->filled('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by access policy
        if ($request->filled('access_policy')) {
            $query->where('access_policy', $request->access_policy);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $bookFiles = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            BookFileResource::collection($bookFiles),
            'Book files retrieved successfully'
        );
    }

    public function store(StoreBookFileRequest $request): JsonResponse
    {
        $bookFile = BookFile::create($request->validated());

        $bookFile->load('book');

        return $this->successResponse(
            new BookFileResource($bookFile),
            'Book file created successfully',
            201
        );
    }

    public function show(BookFile $bookFile): JsonResponse
    {
        $bookFile->load('book');

        return $this->successResponse(
            new BookFileResource($bookFile),
            'Book file retrieved successfully'
        );
    }

    public function update(UpdateBookFileRequest $request, BookFile $bookFile): JsonResponse
    {
        $bookFile->update($request->validated());

        $bookFile->load('book');

        return $this->successResponse(
            new BookFileResource($bookFile),
            'Book file updated successfully'
        );
    }

    public function destroy(BookFile $bookFile): JsonResponse
    {
        $bookFile->delete();

        return $this->successResponse(
            null,
            'Book file deleted successfully'
        );
    }

    public function download(BookFile $bookFile): JsonResponse
    {
        $user = auth()->user();

        if (!$bookFile->canAccess($user)) {
            return $this->errorResponse(
                'You do not have permission to access this file.',
                403
            );
        }

        $url = $bookFile->getUrl();

        if (!$url) {
            return $this->errorResponse(
                'File not available for download.',
                404
            );
        }

        return $this->successResponse(
            ['download_url' => $url],
            'Download URL generated successfully'
        );
    }
}
