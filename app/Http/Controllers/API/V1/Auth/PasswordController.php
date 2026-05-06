<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Email\EmailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    /**
     * Request a password reset link (email). Response is uniform whether the user exists.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|email|max:255',
            'type' => 'sometimes|string|in:email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $identifier = $request->string('identifier')->trim()->toString();
        $user = User::where('identifier', $identifier)->first();

        $message = 'Se o identificador existir no nosso sistema, receberá instruções por email.';

        if (!$user) {
            return response()->json(['message' => $message], 200);
        }

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['identifier' => $user->identifier],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        $this->emailService->sendPasswordResetEmail($user, $plainToken);

        return response()->json(['message' => $message], 200);
    }

    /**
     * Complete password reset using token from email link.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $identifier = $request->string('identifier')->trim()->toString();
        $record = DB::table('password_reset_tokens')->where('identifier', $identifier)->first();

        if (!$record || !Hash::check($request->input('token'), $record->token)) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
                'code' => 'password_reset_invalid',
            ], 422);
        }

        $expiresMinutes = (int) config('auth.passwords.users.expire', 60);
        $issued = Carbon::parse($record->created_at);
        if ($issued->lt(now()->subMinutes($expiresMinutes))) {
            DB::table('password_reset_tokens')->where('identifier', $identifier)->delete();

            return response()->json([
                'message' => 'Token inválido ou expirado.',
                'code' => 'password_reset_expired',
            ], 422);
        }

        $user = User::where('identifier', $identifier)->first();
        if (!$user) {
            DB::table('password_reset_tokens')->where('identifier', $identifier)->delete();

            return response()->json([
                'message' => 'Token inválido ou expirado.',
                'code' => 'password_reset_invalid',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->input('password')),
            'must_change' => false,
        ]);

        DB::table('password_reset_tokens')->where('identifier', $identifier)->delete();

        return response()->json([
            'message' => 'Palavra-passe atualizada com sucesso.',
        ], 200);
    }

    public function change(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth('api')->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 401);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change' => false,
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }
}
