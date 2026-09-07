<?php

namespace App\Services\NajmHoda;

use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHodaIntegrationService;

class NajmHodaChatEscalationService
{
    public function __construct(protected NajmHodaIntegrationService $integration) {}

    /** @param array<string,mixed> $response */
    public function shouldEscalate(string $userMessage, array $response): bool
    {
        if ((bool) ($response['requires_human_support'] ?? false) || (bool) ($response['escalate_to_support'] ?? false)) {
            return true;
        }

        $text = mb_strtolower($userMessage);
        foreach ([
            'پشتیبان انسانی', 'اپراتور انسانی', 'تیکت بساز', 'تیکت ایجاد کن', 'مشکلم حل نشد',
            'با پشتیبانی', 'human support', 'human agent', 'create a ticket', 'open a ticket', 'not resolved',
        ] as $needle) {
            if (str_contains($text, mb_strtolower($needle))) return true;
        }

        return !(bool) ($response['success'] ?? false)
            && (bool) config('najm-hoda.integration.auto_escalation_on_failure', true);
    }

    /** @param array<string,mixed> $response */
    public function escalate(Conversation $conversation, ?User $user, string $userMessage, array $response): Ticket
    {
        $conversation->loadMissing(['messages' => fn ($q) => $q->orderBy('created_at', 'asc')]);

        $parts = [];
        foreach ($conversation->messages as $message) {
            $role = (string) ($message->role ?? 'unknown');
            $content = trim((string) ($message->content ?? ''));
            if ($content === '') continue;
            $parts[] = '[' . $role . '] ' . $content;
        }

        $failureMessage = trim((string) ($response['message'] ?? ''));
        if (!(bool) ($response['success'] ?? false) && $failureMessage !== '') {
            $parts[] = '[najm_hoda_failure] ' . $failureMessage;
        }

        $transcript = trim(implode("\n\n", $parts));
        if ($transcript === '') $transcript = $userMessage;

        $reason = $this->reason($userMessage, $response);

        return $this->integration->handleEscalation([
            'conversation_id' => 'najm-' . $conversation->id,
            'transcript' => mb_substr($transcript, 0, 50000),
            'user_email' => $user?->email,
            'user_id' => $user?->id,
            'reason' => $reason,
        ]);
    }

    /** @param array<string,mixed> $response */
    protected function reason(string $userMessage, array $response): string
    {
        if (!(bool) ($response['success'] ?? false)) return 'ارجاع خودکار مکالمه حل‌نشده نجم هدا';
        return 'درخواست کاربر برای پشتیبانی انسانی';
    }
}
