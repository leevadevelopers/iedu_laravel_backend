<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreLoanRequest;
use App\Http\Resources\Library\LoanResource;
use App\Models\V1\Library\BookCopy;
use App\Models\V1\Library\Loan;
use App\Events\Library\BookLoaned;
use App\Events\Library\BookReturned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // $this->authorize('viewAny', Loan::class);

        $query = Loan::with(['bookCopy.book', 'borrower']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('borrower_id')) {
            $query->where('borrower_id', $request->borrower_id);
        }

        $loans = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            LoanResource::collection($loans),
            'Loans retrieved successfully'
        );
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $bookCopy = BookCopy::findOrFail($request->book_copy_id);

        if (!$bookCopy->isAvailable()) {
            return $this->errorResponse('Book copy is not available', 422);
        }

        $loan = Loan::create([
            'book_copy_id' => $bookCopy->id,
            'borrower_id' => $request->borrower_id ?? auth()->id(),
            'loaned_at' => now(),
            'due_at' => now()->addDays($request->loan_days ?? 14),
            'status' => 'active',
            'notes' => $request->notes,
        ]);

        $bookCopy->update(['status' => 'loaned']);

        event(new BookLoaned($loan));

        return $this->successResponse(
            new LoanResource($loan->load(['bookCopy.book', 'borrower'])),
            'Book loaned successfully',
            201
        );
    }

    public function show(Loan $loan): JsonResponse
    {
        // $this->authorize('view', $loan);

        return $this->successResponse(
            new LoanResource($loan->load(['bookCopy.book', 'borrower'])),
            'Loan retrieved successfully'
        );
    }

    public function return(Loan $loan): JsonResponse
    {
        // $this->authorize('return', $loan);

        if ($loan->returned_at) {
            return $this->errorResponse('Book already returned', 422);
        }

        $loan->update([
            'returned_at' => now(),
            'status' => 'returned',
        ]);

        $loan->bookCopy->update(['status' => 'available']);

        event(new BookReturned($loan));

        return $this->successResponse(
            new LoanResource($loan),
            'Book returned successfully'
        );
    }

    public function myLoans(Request $request): JsonResponse
    {
        $loans = Loan::where('borrower_id', auth()->id())
            ->with(['bookCopy.book'])
            ->latest()
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            LoanResource::collection($loans),
            'Your loans retrieved successfully'
        );
    }

    public function overdue(Request $request): JsonResponse
    {
        $loans = Loan::whereNull('returned_at')
            ->where('due_at', '<', now())
            ->with(['bookCopy.book', 'borrower'])
            ->orderBy('due_at')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            LoanResource::collection($loans),
            'Overdue loans retrieved successfully'
        );
    }

    public function renew(Request $request, Loan $loan): JsonResponse
    {
        // $this->authorize('renew', $loan);

        if ($loan->returned_at) {
            return $this->errorResponse('Cannot renew a returned loan', 422);
        }

        $days = (int) ($request->get('days', 7));
        if ($days < 1 || $days > 30) {
            return $this->errorResponse('Renewal days must be between 1 and 30', 422);
        }

        $loan->update([
            'due_at' => $loan->due_at->addDays($days),
        ]);

        return $this->successResponse(
            new LoanResource($loan),
            'Loan renewed successfully'
        );
    }

    public function destroy(Loan $loan): JsonResponse
    {
        // $this->authorize('delete', $loan);

        if ($loan->returned_at === null) {
            return $this->errorResponse('Cannot delete an active loan. Return the book first.', 422);
        }

        $loan->delete();

        return $this->successResponse(null, 'Loan deleted successfully');
    }
}
