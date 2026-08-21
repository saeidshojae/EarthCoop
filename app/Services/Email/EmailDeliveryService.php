<?php

namespace App\Services\Email;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailDeliveryService
{
    /**
     * @param array<int,string> $recipients
     * @return array{sent_count:int,failed_count:int,recipients:array<int,string>}
     */
    public function sendHtml(array $recipients, string $subject, string $body): array
    {
        $valid = array_values(array_unique(array_filter(array_map(
            static fn ($email): string => trim((string) $email),
            $recipients
        ), static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));

        $sent = 0;
        $failed = 0;

        foreach ($valid as $recipient) {
            try {
                Mail::html($body, function ($message) use ($recipient, $subject): void {
                    $message->to($recipient)->subject($subject);
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
