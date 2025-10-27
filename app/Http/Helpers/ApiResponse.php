<?php

namespace App\Http\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * Generate a unique trace ID for error tracking
     */
    private static function generateTraceId(): string
    {
        return uniqid('trace_', true);
    }

    /**
     * Format a success response
     */
    public static function success($data, $meta = null, $links = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        if ($links !== null) {
            $response['links'] = $links;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Format an error response
     */
    public static function error(string $message, string $code, $errors = null, int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // Add trace_id in development environment
        if (config('app.env') !== 'production') {
            $response['trace_id'] = self::generateTraceId();
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Format a paginated response
     */
    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return self::success(
            $paginator->items(),
            [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total()
            ],
            [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl()
            ]
        );
    }

    /**
     * Format a created response (201)
     */
    public static function created($data, $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, 201);
    }

    /**
     * Format a no content response (204)
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
