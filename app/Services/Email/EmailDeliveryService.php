<?php

namespace App\Services\Email;

use App\Services\Communication\CommunicationDispatcher;
use Illuminate\Support\Str;

/**
 * @deprecated Compatibility facade for older callers. New code should depend on
 * CommunicationDispatcher directly. This class no longer talks to the mail
 * provider and cannot bypass the canonical Communication Center.
 */
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

        if ($valid === []) {
            return [
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients' => [],
            ];
        }

        app(CommunicationDispatcher::class)->dispatchExternal(
            'admin.manual_custom',
            ['type' => 'legacy.email_delivery', 'id' => (string) Str::uuid()],
            array_map(
                static fn (string $email): array => ['email' => $email, 'locale' => 'fa'],
                $valid,
            ),
            [
                'subject' => $subject,
                'rendered_html' => $body,
            ],
            [
                'priority' => 2,
                'deduplication_key' => 'legacy.email_delivery:'.Str::uuid(),
            ],
        );

        // Backward-compatible result shape: accepted canonical recipients are
        // counted as sent by legacy callers even though provider delivery is async.
        return [
            'sent_count' => count($valid),
            'failed_count' => 0,
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
