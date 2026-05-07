<?php

namespace App\Services\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SignupIdempotencyService
{
    public function validateKeyFormat(string $key): ?JsonResponse
    {
        if ($this->isValidUuid($key)) {
            return null;
        }

        return response()->json([
            'errors' => [
                'idempotency_key' => ['The Idempotency-Key must be a valid UUID.'],
            ],
        ], 422);
    }

    public function isValidUuid(string $key): bool
    {
        return Str::isUuid($key);
    }

    /**
     * Canonical hash of signup intent for idempotency comparison.
     */
    public function hashSignupPayload(Request $request): string
    {
        $fields = [
            'name',
            'identifier',
            'type',
            'password',
            'password_confirmation',
            'organization_name',
            'school_name',
            'country_code',
            'timezone',
            'locale',
        ];

        $canonical = [];
        foreach ($fields as $f) {
            $v = $request->input($f);
            if ($f === 'identifier' && $request->input('type') === 'email' && is_string($v)) {
                $v = strtolower(trim($v));
            } elseif (is_string($v)) {
                $v = trim($v);
            }
            $canonical[$f] = $v;
        }

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE));
    }

    public function cacheKey(string $idempotencyKey): string
    {
        return 'signup:idempotency:'.$idempotencyKey;
    }

    public function lockKey(string $idempotencyKey): string
    {
        return 'signup:idempotency-lock:'.$idempotencyKey;
    }

    /**
     * @return array{payload_hash: string, body: string, status: int}|null
     */
    public function getCachedEntry(string $idempotencyKey): ?array
    {
        $entry = Cache::get($this->cacheKey($idempotencyKey));

        return is_array($entry)
            && isset($entry['payload_hash'], $entry['body'], $entry['status'])
            ? $entry
            : null;
    }

    public function rememberSuccess(string $idempotencyKey, string $payloadHash, JsonResponse $response): void
    {
        Cache::put(
            $this->cacheKey($idempotencyKey),
            [
                'payload_hash' => $payloadHash,
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
            ],
            now()->addSeconds(config('iedu.signup_idempotency_ttl', 600))
        );
    }

    public function ttlSeconds(): int
    {
        return (int) config('iedu.signup_idempotency_ttl', 600);
    }
}
