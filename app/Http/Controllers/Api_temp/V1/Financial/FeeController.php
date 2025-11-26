<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\StoreFeeRequest;
use App\Http\Requests\Financial\UpdateFeeRequest;
use App\Http\Resources\Financial\InvoiceResource;
use App\Models\V1\Financial\Fee;
use App\Models\V1\Financial\Invoice;
use App\Models\User;
use App\Events\Financial\FeeApplied;
use App\Http\Resources\Financial\FeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(Fee::class, 'fee');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Fee::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $fees = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            FeeResource::collection($fees),
            'Fees retrieved successfully'
        );
    }

    public function store(StoreFeeRequest $request): JsonResponse
    {
        $fee = Fee::create($request->validated());

        return $this->successResponse(
            new FeeResource($fee),
            'Fee created successfully',
            201
        );
    }

    public function show(Fee $fee): JsonResponse
    {
        return $this->successResponse(
            new FeeResource($fee),
            'Fee retrieved successfully'
        );
    }

    public function update(UpdateFeeRequest $request, Fee $fee): JsonResponse
    {
        $fee->update($request->validated());

        return $this->successResponse(
            new FeeResource($fee),
            'Fee updated successfully'
        );
    }

    public function apply(Request $request): JsonResponse
    {
        // $this->authorize('apply', Fee::class);

        $request->validate([
            'fee_id' => 'required|exists:fees,id',
            'user_id' => 'required|exists:users,id',
            'due_at' => 'required|date',
        ]);

        $fee = Fee::findOrFail($request->fee_id);
        $user = User::findOrFail($request->user_id);

        $invoice = Invoice::create([
            'billable_id' => $user->id,
            'billable_type' => User::class,
            'subtotal' => $fee->amount,
            'total' => $fee->amount,
            'status' => 'issued',
            'issued_at' => now(),
            'due_at' => $request->due_at,
        ]);

        $invoice->items()->create([
            'description' => $fee->name,
            'quantity' => 1,
            'unit_price' => $fee->amount,
            'total' => $fee->amount,
            'fee_id' => $fee->id,
        ]);

        event(new FeeApplied($fee, $user, $invoice));

        return $this->successResponse(
            new InvoiceResource($invoice->load(['items', 'billable'])),
            'Fee applied successfully',
            201
        );
    }

    public function bulkApply(Request $request): JsonResponse
    {
        // $this->authorize('apply', Fee::class);

        $request->validate([
            'fee_id' => 'required|exists:fees,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'due_at' => 'required|date',
        ]);

        $fee = Fee::findOrFail($request->fee_id);
        $users = User::whereIn('id', $request->user_ids)->get();

        $created = [];

        DB::beginTransaction();
        try {
            foreach ($users as $user) {
                $invoice = Invoice::create([
                    'billable_id' => $user->id,
                    'billable_type' => User::class,
                    'subtotal' => $fee->amount,
                    'total' => $fee->amount,
                    'status' => 'issued',
                    'issued_at' => now(),
                    'due_at' => $request->due_at,
                ]);

                $invoice->items()->create([
                    'description' => $fee->name,
                    'quantity' => 1,
                    'unit_price' => $fee->amount,
                    'total' => $fee->amount,
                    'fee_id' => $fee->id,
                ]);

                event(new FeeApplied($fee, $user, $invoice));

                $created[] = new InvoiceResource($invoice->load(['items', 'billable']));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to bulk apply fee: ' . $e->getMessage(), 500);
        }

        return $this->successResponse([
            'created_count' => count($created),
            'invoices' => $created,
        ], 'Fee bulk-applied successfully');
    }
}
