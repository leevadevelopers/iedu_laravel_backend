<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\StoreExpenseRequest;
use App\Http\Requests\Financial\UpdateExpenseRequest;
use App\Http\Resources\Financial\ExpenseResource;
use App\Models\V1\Financial\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Expense::with(['account', 'creator']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by account
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('incurred_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('incurred_at', '<=', $request->date_to);
        }

        // Filter by amount range
        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->amount_max);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'incurred_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $expenses = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            ExpenseResource::collection($expenses),
            'Expenses retrieved successfully'
        );
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Garantir que tenant_id e school_id não sejam definidos manualmente
        unset($validated['tenant_id'], $validated['school_id']);

        $expense = Expense::create($validated);

        $expense->load(['account', 'creator']);

        return $this->successResponse(
            new ExpenseResource($expense),
            'Expense created successfully',
            201
        );
    }

    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['account', 'creator']);

        return $this->successResponse(
            new ExpenseResource($expense),
            'Expense retrieved successfully'
        );
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $validated = $request->validated();

        // Garantir que tenant_id e school_id não sejam atualizados manualmente
        unset($validated['tenant_id'], $validated['school_id']);

        $expense->update($validated);

        $expense->load(['account', 'creator']);

        return $this->successResponse(
            new ExpenseResource($expense),
            'Expense updated successfully'
        );
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return $this->successResponse(
            null,
            'Expense deleted successfully'
        );
    }
}
