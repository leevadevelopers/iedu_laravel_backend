<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\StoreFinancialAccountRequest;
use App\Http\Requests\Financial\UpdateFinancialAccountRequest;
use App\Http\Resources\Financial\FinancialAccountResource;
use App\Models\V1\Financial\FinancialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialAccountController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(FinancialAccount::class, 'financialAccount');
    }

    public function index(Request $request): JsonResponse
    {
        $query = FinancialAccount::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sort
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $accounts = $query->withCount(['transactions', 'expenses'])->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            FinancialAccountResource::collection($accounts),
            'Financial accounts retrieved successfully'
        );
    }

    public function store(StoreFinancialAccountRequest $request): JsonResponse
    {
        $account = FinancialAccount::create($request->validated());

        return $this->successResponse(
            new FinancialAccountResource($account),
            'Financial account created successfully',
            201
        );
    }

    public function show(FinancialAccount $account): JsonResponse
    {
        $account->loadCount(['transactions', 'expenses']);

        return $this->successResponse(
            new FinancialAccountResource($account),
            'Financial account retrieved successfully'
        );
    }

    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $account): JsonResponse
    {
        $account->update($request->validated());

        return $this->successResponse(
            new FinancialAccountResource($account),
            'Financial account updated successfully'
        );
    }

    public function destroy(FinancialAccount $account): JsonResponse
    {
        // Check if account has transactions or expenses
        if ($account->transactions()->count() > 0 || $account->expenses()->count() > 0) {
            return $this->errorResponse(
                'Cannot delete account with transactions or expenses. Please remove all related records first.',
                422
            );
        }

        $account->delete();

        return $this->successResponse(
            null,
            'Financial account deleted successfully'
        );
    }
}
