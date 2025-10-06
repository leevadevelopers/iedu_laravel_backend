<?php

namespace App\Http\Controllers\API\V1\Library;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Library\StoreReservationRequest;
use App\Http\Resources\Library\ReservationResource;
use App\Models\V1\Library\Reservation;
use App\Events\Library\ReservationCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // $this->authorize('viewAny', Reservation::class);

        $query = Reservation::with(['book', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reservations = $query->latest()->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            ReservationResource::collection($reservations),
            'Reservations retrieved successfully'
        );
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $reservation = Reservation::create([
            'book_id' => $request->book_id,
            'user_id' => auth()->id(),
            'reserved_at' => now(),
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        event(new ReservationCreated($reservation));

        return $this->successResponse(
            new ReservationResource($reservation->load(['book', 'user'])),
            'Reservation created successfully',
            201
        );
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->authorize('cancel', $reservation);

        $reservation->update(['status' => 'cancelled']);

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reservation cancelled successfully'
        );
    }
}
