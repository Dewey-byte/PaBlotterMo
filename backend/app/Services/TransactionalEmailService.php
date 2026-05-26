<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TransactionalEmailService
{
    /**
     * @param  non-empty-list<string>  $toAddresses
     * @param  array<string, mixed>  $logContext
     * @return array{sent: bool, reason: string|null}
     */
    public function send(array $toAddresses, string $subject, string $textBody, array $logContext = []): array
    {
        $toAddresses = array_values(array_filter(array_map(
            static fn (string $address): string => trim($address),
            $toAddresses,
        )));

        if ($toAddresses === []) {
            return [
                'sent' => false,
                'reason' => 'No valid recipient addresses.',
            ];
        }

        if ($this->smtpIsConfigured()) {
            return $this->sendViaSmtp($toAddresses, $subject, $textBody, $logContext);
        }

        if ($this->resendIsConfigured()) {
            $result = $this->sendViaResend($toAddresses, $subject, $textBody, $logContext);

            if ($result['sent']) {
                return $result;
            }

            return [
                'sent' => false,
                'reason' => $result['reason']
                    ?? 'Resend could not deliver the message. Configure MAIL_* SMTP in .env to send to any email address.',
            ];
        }

        return [
            'sent' => false,
            'reason' => 'Email is not configured. Set MAIL_USERNAME and MAIL_PASSWORD (SMTP) or RESEND_API_KEY in .env.',
        ];
    }

    /**
     * @param  non-empty-list<string>  $toAddresses
     * @param  array<string, mixed>  $logContext
     * @return array{sent: bool, reason: string|null}
     */
    private function sendViaSmtp(array $toAddresses, string $subject, string $textBody, array $logContext): array
    {
        try {
            Mail::raw($textBody, function (Message $message) use ($toAddresses, $subject): void {
                $message->to($toAddresses)->subject($subject);
            });

            return [
                'sent' => true,
                'reason' => null,
            ];
        } catch (Throwable $exception) {
            Log::error('SMTP email request failed.', array_merge($logContext, [
                'subject' => $subject,
                'recipients' => $toAddresses,
                'error' => $exception->getMessage(),
            ]));

            return [
                'sent' => false,
                'reason' => 'SMTP mail delivery failed. Check MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD in .env.',
            ];
        }
    }

    /**
     * @param  non-empty-list<string>  $toAddresses
     * @param  array<string, mixed>  $logContext
     * @return array{sent: bool, reason: string|null}
     */
    private function sendViaResend(array $toAddresses, string $subject, string $textBody, array $logContext): array
    {
        $apiKey = (string) config('services.resend.key', '');
        $fromAddress = (string) env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');

        try {
            Http::withToken($apiKey)
                ->acceptJson()
                ->post('https://api.resend.com/emails', [
                    'from' => $fromAddress,
                    'to' => $toAddresses,
                    'subject' => $subject,
                    'text' => $textBody,
                ])
                ->throw();

            return [
                'sent' => true,
                'reason' => null,
            ];
        } catch (RequestException $exception) {
            $status = optional($exception->response)->status();
            $body = (string) optional($exception->response)->body();

            Log::error('Resend email request failed.', array_merge($logContext, [
                'subject' => $subject,
                'recipients' => $toAddresses,
                'status' => $status,
                'body' => $body,
                'error' => $exception->getMessage(),
            ]));

            if ($status === 403 && str_contains($body, 'testing emails')) {
                return [
                    'sent' => false,
                    'reason' => 'Resend test mode only allows sending to your own verified email. Configure Gmail SMTP (MAIL_*) in .env to notify residents.',
                ];
            }

            return [
                'sent' => false,
                'reason' => 'Email provider rejected the message.',
            ];
        }
    }

    private function smtpIsConfigured(): bool
    {
        if ((string) config('mail.default') !== 'smtp') {
            return false;
        }

        $username = trim((string) config('mail.mailers.smtp.username'));
        $password = trim((string) config('mail.mailers.smtp.password'));

        if ($username === '' || $password === '') {
            return false;
        }

        return ! str_contains($username, 'yourgmail');
    }

    private function resendIsConfigured(): bool
    {
        $apiKey = (string) config('services.resend.key', '');

        return $apiKey !== '' && ! str_contains($apiKey, 'your_resend_api_key');
    }
}
