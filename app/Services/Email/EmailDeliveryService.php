<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailDeliveryService
{
    /**
     * @param array<int,string> $recipients
     * @param array{address?:string,name?:string,reply_to?:string}|null $from
     * @return array{sent_count:int,failed_count:int,recipients:array<int,string>}
     */
    public function sendHtml(array $recipients, string $subject, string $body, ?array $from = null): array
    {
        $valid = array_values(array_unique(array_filter(array_map(
            static fn ($email): string => trim((string) $email),
            $recipients
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));

        $sent = 0;
        $failed = 0;
        $fromAddress = trim((string) ($from['address'] ?? ''));
        $fromName = trim((string) ($from['name'] ?? ''));
        $replyTo = trim((string) ($from['reply_to'] ?? ''));

        foreach ($valid as $recipient) {
            try {
                Mail::html($body, function ($message) use ($recipient, $subject, $fromAddress, $fromName, $replyTo): void {
                    $message->to($recipient)->subject($subject);
                    if ($fromAddress !== '') {
                        $message->from($fromAddress, $fromName !== '' ? $fromName : null);
                    }
                    if ($replyTo !== '') {
                        $message->replyTo($replyTo, $fromName !== '' ? $fromName : null);
                    }
                });
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Failed to send email', [
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'sent_count' => $sent,
            'failed_count' => $failed,
            'recipients' => $valid,
        ];
    }

    /** @param array<int,string> $raw */
    public function parseRecipients(array $raw): array
    {
        $emails = [];
        foreach ($raw as $value) {
            foreach (preg_split('/[,\n]/', (string) $value) ?: [] as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }

        return array_values(array_unique($emails));
    }
}
