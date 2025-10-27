<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\StorePaymentRequest;
use App\Http\Resources\Financial\PaymentResource;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\Payment;
use App\Events\Financial\InvoicePaid;
use App\Events\Financial\PaymentCompleted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // $this->authorize('viewAny', Payment::class);

        $query = Payment::with(['invoice']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        $payments = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            PaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        // $this->authorize('view', $payment);

        return $this->successResponse(
            new PaymentResource($payment->load('invoice')),
            'Payment retrieved successfully'
        );
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $invoice = Invoice::findOrFail($request->invoice_id);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $request->validated()['amount'],
            'method' => $request->validated()['method'],
            'status' => 'completed',
            'paid_at' => now(),
            'transaction_id' => $request->validated()['transaction_id'] ?? null,
            'notes' => $request->validated()['notes'] ?? null,
        ]);

        // Update invoice status
        $remaining = $invoice->getRemainingBalance();
        if ($remaining <= 0) {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            event(new InvoicePaid($invoice));
        } elseif ($remaining < $invoice->total) {
            $invoice->update(['status' => 'partially_paid']);
        }

        event(new PaymentCompleted($payment));

        return $this->successResponse(
            new PaymentResource($payment->load('invoice')),
            'Payment processed successfully',
            201
        );
    }

    public function refund(Payment $payment, Request $request): JsonResponse
    {
        // $this->authorize('update', $payment);

        if ($payment->status === 'refunded') {
            return $this->errorResponse('Payment already refunded', 422);
        }

        $payment->update([
            'status' => 'refunded',
            'notes' => trim(($payment->notes ? $payment->notes . ' ' : '') . ($request->get('reason') ? 'Refund: ' . $request->get('reason') : '')),
        ]);

        // Recalculate invoice status after refund
        $invoice = $payment->invoice()->first();
        if ($invoice) {
            $remaining = $invoice->getRemainingBalance();
            if ($remaining <= 0) {
                $invoice->update(['status' => 'paid', 'paid_at' => $invoice->paid_at ?? now()]);
            } elseif ($remaining < $invoice->total) {
                $invoice->update(['status' => 'partially_paid', 'paid_at' => null]);
            } else {
                $invoice->update(['status' => 'unpaid', 'paid_at' => null]);
            }
        }

        return $this->successResponse(
            new PaymentResource($payment->fresh()->load('invoice')),
            'Payment refunded successfully'
        );
    }

    public function receipt(Payment $payment): JsonResponse
    {
        // $this->authorize('view', $payment);

        $payload = [
            'payment' => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
                'transaction_id' => $payment->transaction_id,
                'notes' => $payment->notes,
            ],
            'invoice' => $payment->invoice ? [
                'id' => $payment->invoice->id,
                'reference' => $payment->invoice->reference,
                'total' => (float) $payment->invoice->total,
                'status' => $payment->invoice->status,
                'issued_at' => $payment->invoice->issued_at?->toISOString(),
                'due_at' => $payment->invoice->due_at?->toISOString(),
            ] : null,
        ];

        return $this->successResponse($payload, 'Payment receipt generated successfully');
    }
}
