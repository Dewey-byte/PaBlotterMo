<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminPasswordOtp;
use App\Models\User;
use App\Services\TransactionalEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 422);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Only administrators can login.',
            ], 403);
        }

        $token = $user->createToken('admin-session')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'contactNumber' => $user->contact_number,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function requestForgotPasswordOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', 'admin')
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'No admin account found for this email.',
            ], 404);
        }

        $latestOtp = AdminPasswordOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (
            $latestOtp
            && $latestOtp->created_at
            && $latestOtp->created_at->isAfter(now()->subSeconds($this->otpResendCooldownSeconds()))
        ) {
            return response()->json([
                'message' => 'Please wait before requesting another OTP.',
            ], 429);
        }

        if ($latestOtp && $latestOtp->expires_at && $latestOtp->expires_at->isPast()) {
            $latestOtp->update(['used_at' => now()]);
            $latestOtp = null;
        }

        $plainOtp = (string) random_int(100000, 999999);

        $otp = AdminPasswordOtp::query()->create([
            'user_id' => $user->id,
            'otp_hash' => Hash::make($plainOtp),
            'expires_at' => now()->addMinutes($this->otpExpiryMinutes()),
        ]);

        try {
            $deliveryMode = $this->sendOtpEmail($user->email, $plainOtp);
        } catch (Throwable $exception) {
            $otp->delete();

            return response()->json([
                'message' => 'Failed to send OTP email. Check RESEND_API_KEY and RESEND_FROM_EMAIL in backend .env.',
            ], 500);
        }

        if ($deliveryMode === 'fallback') {
            $response = [
                'message' => 'Email service is not configured. Use the temporary OTP shown below.',
            ];

            if ($this->canExposeOtpForTesting()) {
                $response['otp'] = $plainOtp;
            }

            return response()->json($response);
        }

        return response()->json([
            'message' => 'OTP sent to your email address.',
        ]);
    }

    public function resetPasswordWithOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->where('role', 'admin')
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'No admin account found for this email.',
            ], 404);
        }

        $otpRecord = AdminPasswordOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otpRecord) {
            return response()->json([
                'message' => 'OTP expired or invalid. Request a new OTP.',
            ], 422);
        }

        if ($otpRecord->attempts >= $this->otpMaxAttempts()) {
            return response()->json([
                'message' => 'Maximum OTP attempts exceeded. Request a new OTP.',
            ], 429);
        }

        if (! Hash::check($validated['otp'], $otpRecord->otp_hash)) {
            $otpRecord->increment('attempts');

            return response()->json([
                'message' => 'Invalid OTP code.',
            ], 422);
        }

        $user->password = Hash::make($validated['newPassword']);
        $user->save();

        $otpRecord->used_at = now();
        $otpRecord->save();

        return response()->json([
            'message' => 'Password reset successful. You can now sign in.',
        ]);
    }

    private function sendOtpEmail(string $emailAddress, string $otp): string
    {
        $message = implode("\n", [
            'Dear Administrator,',
            '',
            "Your one-time password (OTP) for PaBlotterMo password reset is: {$otp}",
            "This OTP will expire in {$this->otpExpiryMinutes()} minutes.",
            '',
            'If you did not request a password reset, please ignore this email.',
            '',
            'Sincerely,',
            'PaBlotterMo System Administration',
        ]);

        $result = app(TransactionalEmailService::class)->send(
            [$emailAddress],
            'Official OTP for Admin Password Reset',
            $message,
            ['context' => 'admin_password_otp', 'to' => $emailAddress],
        );

        if ($result['sent']) {
            return 'sent';
        }

        if ($this->canExposeOtpForTesting()) {
            Log::info('OTP email fallback (no working mail provider).', [
                'to' => $emailAddress,
                'otp' => $otp,
                'reason' => $result['reason'],
            ]);

            return 'fallback';
        }

        throw new RuntimeException($result['reason'] ?? 'Failed to send OTP email.');
    }

    private function canExposeOtpForTesting(): bool
    {
        return (bool) config('app.debug') || app()->environment('local');
    }

    private function otpExpiryMinutes(): int
    {
        return (int) env('ADMIN_OTP_EXPIRY_MINUTES', 10);
    }

    private function otpResendCooldownSeconds(): int
    {
        return (int) env('ADMIN_OTP_RESEND_COOLDOWN_SECONDS', 60);
    }

    private function otpMaxAttempts(): int
    {
        return (int) env('ADMIN_OTP_MAX_ATTEMPTS', 5);
    }
}
