<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\StoreInvoiceRequest;
use App\Http\Requests\Financial\UpdateInvoiceRequest;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\InvoiceItem;
use App\Events\Financial\InvoiceIssued;
use App\Http\Resources\Financial\InvoiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

    public function myInvoices(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return $this->errorResponse('User not authenticated', 401);
        }

        $query = Invoice::with(['billable', 'items', 'payments'])
            ->where('billable_type', \App\Models\User::class)
            ->where('billable_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('issued_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('issued_at', '<=', $request->date_to);
        }

        $invoices = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            InvoiceResource::collection($invoices),
            'My invoices retrieved successfully'
        );
    }

    public function overdue(Request $request): JsonResponse
    {
        $query = Invoice::with(['billable', 'items', 'payments'])
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled']);

        // Optionally filter by billable if provided
        if ($request->filled('billable_type') && $request->filled('billable_id')) {
            $query->where('billable_type', $request->billable_type)
                  ->where('billable_id', $request->billable_id);
        }

        // Filter by date range for due_at
        if ($request->filled('due_from')) {
            $query->where('due_at', '>=', $request->due_from);
        }

        if ($request->filled('due_to')) {
            $query->where('due_at', '<=', $request->due_to);
        }

        $invoices = $query->orderBy('due_at', 'asc')->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            InvoiceResource::collection($invoices),
            'Overdue invoices retrieved successfully'
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Garantir que tenant_id e school_id não sejam definidos manualmente
        unset($validated['tenant_id'], $validated['school_id']);

        $invoice = Invoice::create($validated);

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
        $validated = $request->validated();

        // Garantir que tenant_id e school_id não sejam atualizados manualmente
        unset($validated['tenant_id'], $validated['school_id']);

        $invoice->update($validated);

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

    public function cancel(Invoice $invoice, Request $request): JsonResponse
    {
        // $this->authorize('cancel', $invoice);

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return $this->errorResponse(
                'Cannot cancel invoice with status: ' . $invoice->status,
                422
            );
        }

        $invoice->update([
            'status' => 'cancelled',
            'notes' => trim(($invoice->notes ? $invoice->notes . "\n\n" : '') .
                'Cancelled: ' . ($request->get('reason', 'No reason provided'))),
        ]);

        return $this->successResponse(
            new InvoiceResource($invoice->load(['items', 'billable'])),
            'Invoice cancelled successfully'
        );
    }

    public function send(Invoice $invoice, Request $request): JsonResponse
    {
        // $this->authorize('send', $invoice);

        if ($invoice->status === 'draft') {
            return $this->errorResponse(
                'Cannot send draft invoice. Please issue it first.',
                422
            );
        }

        if ($invoice->status === 'cancelled') {
            return $this->errorResponse(
                'Cannot send cancelled invoice.',
                422
            );
        }

        // Get email from request or from billable
        $email = $request->get('email');
        if (!$email && $invoice->billable) {
            if (method_exists($invoice->billable, 'email')) {
                $email = $invoice->billable->email;
            } elseif (isset($invoice->billable->email)) {
                $email = $invoice->billable->email;
            }
        }

        if (!$email) {
            return $this->errorResponse(
                'Email address is required to send invoice.',
                422
            );
        }

        // TODO: Implement email sending logic
        // Example: Mail::to($email)->send(new InvoiceMail($invoice));

        // For now, just return success
        return $this->successResponse(
            [
                'invoice' => new InvoiceResource($invoice->load(['items', 'billable'])),
                'sent_to' => $email,
            ],
            'Invoice sent successfully'
        );
    }

    public function download(Invoice $invoice): JsonResponse
    {
        // $this->authorize('download', $invoice);

        $invoice->load(['items', 'billable', 'payments']);

        // TODO: Implement PDF generation
        // For now, return invoice data that can be used to generate PDF on frontend
        // Or implement PDF generation using a package like dompdf/barryvdh-laravel-dompdf

        return $this->successResponse(
            [
                'invoice' => new InvoiceResource($invoice),
                'download_url' => null, // Will be set when PDF generation is implemented
                'message' => 'PDF generation not yet implemented. Invoice data returned.',
            ],
            'Invoice data retrieved for download'
        );
    }

    public function addItem(Invoice $invoice, Request $request): JsonResponse
    {
        // $this->authorize('update', $invoice);

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return $this->errorResponse(
                'Cannot add items to invoice with status: ' . $invoice->status,
                422
            );
        }

        $validated = $request->validate([
            'description' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'fee_id' => 'nullable|exists:fees,id',
        ]);

        $item = $invoice->items()->create($validated);

        // Recalculate invoice totals
        $this->recalculateInvoiceTotals($invoice);

        return $this->successResponse(
            new InvoiceResource($invoice->fresh()->load(['items', 'billable'])),
            'Item added to invoice successfully'
        );
    }

    public function removeItem(Invoice $invoice, $item, Request $request): JsonResponse
    {
        // $this->authorize('update', $invoice);

        // Find the item - $item can be ID or InvoiceItem instance
        if (is_numeric($item)) {
            $item = InvoiceItem::findOrFail($item);
        } elseif (!$item instanceof InvoiceItem) {
            return $this->errorResponse('Invalid item', 422);
        }

        if ($item->invoice_id !== $invoice->id) {
            return $this->errorResponse(
                'Item does not belong to this invoice',
                422
            );
        }

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return $this->errorResponse(
                'Cannot remove items from invoice with status: ' . $invoice->status,
                422
            );
        }

        $item->delete();

        // Recalculate invoice totals
        $this->recalculateInvoiceTotals($invoice);

        return $this->successResponse(
            new InvoiceResource($invoice->fresh()->load(['items', 'billable'])),
            'Item removed from invoice successfully'
        );
    }

    /**
     * Recalculate invoice totals based on items
     */
    protected function recalculateInvoiceTotals(Invoice $invoice): void
    {
        $items = $invoice->items;

        $subtotal = $items->sum('total');
        $tax = $invoice->tax ?? 0;
        $discount = $invoice->discount ?? 0;
        $total = $subtotal + $tax - $discount;

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => max(0, $total), // Ensure total is not negative
        ]);
    }
}
