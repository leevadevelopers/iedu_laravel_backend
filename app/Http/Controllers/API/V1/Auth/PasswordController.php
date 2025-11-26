<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('identifier', $request->identifier)->first();

        if (!$user) {
            return response()->json(['message' => 'If your identifier exists in our system, you will receive a password reset email.'], 200);
        }

        // Generate a temporary password
        $tempPassword = Str::random(8);

        // Update user with temporary password and must_change flag
        $user->update([
            'password' => Hash::make($tempPassword),
            'must_change' => true
        ]);

        // TODO: Send email/SMS with temporary password based on user type
        // For now, we'll return it in the response (in production, remove this)
        return response()->json([
            'message' => 'A temporary password has been generated.',
            'temporary_password' => $tempPassword // Remove this in production
        ]);
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
            'must_change' => false
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }
}
