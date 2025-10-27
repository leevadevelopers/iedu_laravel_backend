<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\StoreInvoiceRequest;
use App\Http\Requests\Financial\UpdateInvoiceRequest;
use App\Models\V1\Financial\Invoice;
use App\Events\Financial\InvoiceIssued;
use App\Http\Resources\Financial\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->authorizeResource(Invoice::class, 'invoice');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['billable', 'items', 'payments']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('billable_type') && $request->filled('billable_id')) {
            $query->where('billable_type', $request->billable_type)
                  ->where('billable_id', $request->billable_id);
        }

        $invoices = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            InvoiceResource::collection($invoices),
            'Invoices retrieved successfully'
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = Invoice::create($request->validated());

        foreach ($request->items as $item) {
            $invoice->items()->create($item);
        }

        $invoice->load(['items', 'billable']);

        return $this->successResponse(
            new InvoiceResource($invoice),
            'Invoice created successfully',
            201
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['items', 'payments', 'billable']);

        return $this->successResponse(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully'
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice->update($request->validated());

        return $this->successResponse(
            new InvoiceResource($invoice->load(['items', 'billable'])),
            'Invoice updated successfully'
        );
    }

    public function issue(Invoice $invoice): JsonResponse
    {
        // $this->authorize('issue', $invoice);

        $invoice->update([
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        event(new InvoiceIssued($invoice));

        return $this->successResponse(
            new InvoiceResource($invoice),
            'Invoice issued successfully'
        );
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $invoice->delete();

        return $this->successResponse(
            null,
            'Invoice deleted successfully'
        );
    }
}
