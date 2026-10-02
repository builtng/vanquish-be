<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\EmailService;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
            'two_factor_code' => 'nullable|string|size:6|regex:/^[0-9]{6}$/',
        ]);

        // Normalize email
        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Log failed login attempt
            \Log::warning('Failed login attempt', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user account is deactivated
        if (!$user->isActive()) {
            \Log::warning('Login attempt for deactivated user', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated. Please contact an administrator.'],
            ]);
        }

        // Check if 2FA is enabled for admin users
        if ($user->two_factor_enabled && ($user->role === 'admin' || $user->role === 'staff')) {
            // If 2FA code is not provided, return response indicating 2FA is required
            if (!$request->has('two_factor_code') || empty($request->two_factor_code)) {
                return response()->json([
                    'requires_two_factor' => true,
                    'message' => 'Two-factor authentication code is required.',
                ], 200);
            }

            // Verify 2FA code
            $google2fa = new Google2FA();
            $valid = $google2fa->verifyKey(
                $user->two_factor_secret,
                $request->two_factor_code,
                2 // Allow 2 time steps (60 seconds) of tolerance
            );

            // Also check recovery codes
            if (!$valid && $user->two_factor_recovery_codes) {
                $recoveryCodes = is_array($user->two_factor_recovery_codes) 
                    ? $user->two_factor_recovery_codes 
                    : json_decode($user->two_factor_recovery_codes, true);
                
                if (in_array($request->two_factor_code, $recoveryCodes)) {
                    // Remove used recovery code
                    $recoveryCodes = array_values(array_diff($recoveryCodes, [$request->two_factor_code]));
                    $user->update(['two_factor_recovery_codes' => $recoveryCodes]);
                    $valid = true;
                }
            }

            if (!$valid) {
                throw ValidationException::withMessages([
                    'two_factor_code' => ['Invalid two-factor authentication code.'],
                ]);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        // In production, registration should be disabled or restricted
        if (config('app.env') === 'production' && !config('app.allow_registration', false)) {
            return response()->json([
                'message' => 'Registration is disabled.',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255|regex:/^[a-zA-Z\s\-\'\.]+$/',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',      // Must contain at least one lowercase letter
                'regex:/[A-Z]/',      // Must contain at least one uppercase letter
                'regex:/[0-9]/',      // Must contain at least one digit
            ],
            'role' => 'nullable|string|in:admin,staff',
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'password.min' => 'Password must be at least 12 characters.',
        ]);

        // Only allow admin role creation in development or if explicitly allowed
        $role = $request->role ?? 'admin';
        if ($role === 'admin' && config('app.env') === 'production' && !config('app.allow_admin_registration', false)) {
            $role = 'staff';
        }

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower(trim($request->email)), // Normalize email
            'password' => Hash::make($request->password),
            'role' => $role,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Log registration
        \Log::info('New user registered', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'user_registered',
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => "User {$user->name} ({$user->email}) registered with role: {$role}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $this->formatUserResponse($request->user()),
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUserResponse($user->fresh()),
        ]);
    }

    /**
     * Format user payload with counsellor/practitioner details if available
     */
    private function formatUserResponse(User $user): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'two_factor_enabled' => $user->two_factor_enabled,
            'training_counsellor_id' => $user->training_counsellor_id,
            'photo' => null,
            'photo_url' => null,
        ];

        if ($user->training_counsellor_id && $user->trainingCounsellor) {
            $data['photo'] = $user->trainingCounsellor->photo;
            $data['photo_url'] = $user->trainingCounsellor->photo_url;
            $data['tc_uuid'] = $user->trainingCounsellor->uuid;
        }

        return $data;
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'new_password_confirmation' => 'required|string',
        ], [
            'new_password.min' => 'Password must be at least 12 characters.',
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Initiate 2FA setup
     */
    public function initiate2FASetup(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'admin' && $user->role !== 'staff') {
            return response()->json(['message' => '2FA is only available for admin and staff users.'], 403);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Vanquish Therapy',
            $user->email,
            $secret
        );

        // Store secret temporarily (user hasn't confirmed yet)
        $user->update(['two_factor_secret' => $secret]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    /**
     * Verify and enable 2FA
     */
    public function verify2FASetup(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        
        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Please initiate 2FA setup first.'], 400);
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->code, 2);

        if (!$valid) {
            return response()->json(['message' => 'Invalid verification code.'], 422);
        }

        // Generate recovery codes
        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        // Invalidate all existing tokens to force re-authentication with 2FA
        $user->tokens()->delete();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $user = $request->user();
        
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json(['message' => 'Two-factor authentication disabled successfully.']);
    }

    /**
     * Regenerate 2FA QR code
     */
    public function regenerate2FACode(Request $request)
    {
        $user = $request->user();
        
        if ($user->role !== 'admin' && $user->role !== 'staff') {
            return response()->json(['message' => '2FA is only available for admin and staff users.'], 403);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            'Vanquish Therapy',
            $user->email,
            $secret
        );

        $user->update(['two_factor_secret' => $secret]);

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    /**
     * Delete the authenticated user's account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // If user is an admin, ensure there is at least one other admin in the system
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Cannot delete the only remaining admin account in the system.',
                ], 422);
            }
        }

        $userName = $user->name;
        $userEmail = $user->email;
        $userId = $user->id;

        // Revoke all tokens
        $user->tokens()->delete();

        // Log activity before deletion
        ActivityLog::create([
            'user_id' => $userId,
            'action' => 'user_deleted',
            'model_type' => User::class,
            'model_id' => $userId,
            'description' => "User {$userName} ({$userEmail}) deleted their own account",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }

    /**
     * Request a password reset link
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($request->email));
        $user = User::where('email', $email)->first();

        // If user exists and is active, issue token and send email
        if ($user && $user->isActive()) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => hash('sha256', $token),
                'created_at' => now(),
            ]);

            $baseUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');
            $resetUrl = $baseUrl . '/reset-password?token=' . $token . '&email=' . urlencode($email);

            try {
                app(EmailService::class)->sendAndLog(
                    $email,
                    'password_reset',
                    [
                        'name' => $user->name,
                        'reset_link' => $resetUrl,
                        'link' => $resetUrl,
                        'expires_in' => '60 minutes',
                    ]
                );
            } catch (\Exception $e) {
                \Log::error("Failed to send password reset email to {$email}: " . $e->getMessage());
            }

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'password_reset_requested',
                'model_type' => User::class,
                'model_id' => $user->id,
                'description' => "Password reset requested for {$email}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Always return generic success response to prevent email enumeration
        return response()->json([
            'message' => 'If an active account exists with that email address, a password reset link has been sent.',
        ]);
    }

    /**
     * Reset password using token
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
            ],
            'password_confirmation' => 'required|string',
        ], [
            'password.min' => 'Password must be at least 12 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
        ]);

        $email = strtolower(trim($request->email));
        $token = $request->token;

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !hash_equals($record->token, hash('sha256', $token))) {
            throw ValidationException::withMessages([
                'token' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        // Check if token expired (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset link has expired. Please request a new one.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !$user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Unable to reset password for this account.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Invalidate all active tokens for security
        $user->tokens()->delete();

        // Delete reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'password_reset_completed',
            'model_type' => User::class,
            'model_id' => $user->id,
            'description' => "Password reset completed for {$email}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Your password has been reset successfully. You can now log in with your new password.',
        ]);
    }
}

