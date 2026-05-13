<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminPasswordOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        return response()->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'contactNumber' => $user->contact_number,
            ],
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

        if ($latestOtp && $latestOtp->created_at && now()->diffInSeconds($latestOtp->created_at) < $this->otpResendCooldownSeconds()) {
            return response()->json([
                'message' => 'Please wait before requesting another OTP.',
            ], 429);
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
                'message' => 'Failed to send OTP email. Check MAIL_USERNAME, MAIL_PASSWORD (Google App Password), and MAIL_FROM_ADDRESS in backend .env.',
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
        $mailDriver = (string) config('mail.default', 'log');
        $mailUsername = (string) env('MAIL_USERNAME', '');
        $mailPassword = (string) env('MAIL_PASSWORD', '');

        if (in_array($mailDriver, ['log', 'array'], true)) {
            Log::info('OTP email fallback (mailer not configured for real delivery).', [
                'to' => $emailAddress,
                'otp' => $otp,
            ]);

            return 'fallback';
        }

        if ($mailUsername === '' || $mailPassword === '' || str_contains($mailUsername, 'yourgmail@gmail.com') || str_contains($mailPassword, 'your_google_app_password')) {
            Log::info('OTP email fallback (placeholder mail credentials).', [
                'to' => $emailAddress,
                'otp' => $otp,
            ]);

            return 'fallback';
        }

        $message = "Your PaBlotterMo admin OTP is {$otp}. It expires in {$this->otpExpiryMinutes()} minutes.";

        try {
            Mail::raw($message, function ($mail) use ($emailAddress): void {
                $mail
                    ->to($emailAddress)
                    ->subject('PaBlotterMo Admin Password Reset OTP');
            });
            return 'sent';
        } catch (Throwable $exception) {
            Log::error('OTP email sending failed.', [
                'to' => $emailAddress,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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
