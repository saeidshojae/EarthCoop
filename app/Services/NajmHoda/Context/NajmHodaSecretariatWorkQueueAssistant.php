<?php

namespace App\Services\NajmHoda\Context;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Services\SecretariatWorkQueueService;
use Illuminate\Support\Facades\Gate;

/** Read-only S7 advisor for pending approvals, follow-ups and overdue work. */
class NajmHodaSecretariatWorkQueueAssistant
{
    public function __construct(private readonly SecretariatWorkQueueService $workQueue) {}

    /** @param array<string,mixed> $pageContext */
    public function intercept(User $actor, array $pageContext, string $message): ?array
    {
        if (! $this->looksLikeWorkQueueRequest($message)) {
            return null;
        }

        $officeId = $this->officeId($pageContext);
        if ($officeId <= 0) {
            return null;
        }

        $office = SecretariatOffice::query()->find($officeId);
        if (! $office || ! Gate::forUser($actor)->allows('view', $office)) {
            return $this->response('شما به صف کار این دفتر دبیرخانه دسترسی ندارید.', 'blocked');
        }

        $queue = $this->workQueue->forOffice($office, $actor, 30);
        $counts = collect($queue)->map(fn (array $items) => count($items))->all();
        $total = array_sum($counts);

        if ($total === 0) {
            return $this->response('در محدوده مجاز شما، مورد منتظر تأیید، معوق، زمان‌پیگیری‌رسیده یا پاسخ‌موردانتظار فعالی دیده نمی‌شود.', 'ready', ['queue' => $queue, 'counts' => $counts]);
        }

        $lines = [
            'وضعیت صف کار دبیرخانه:',
            'منتظر تأیید: ' . ($counts['pending_approval'] ?? 0),
            'موعد گذشته: ' . ($counts['overdue_dispatches'] ?? 0),
            'زمان پیگیری رسیده: ' . ($counts['follow_up_due'] ?? 0),
            'پاسخ مورد انتظار: ' . ($counts['unanswered_correspondence'] ?? 0),
        ];

        foreach ([
            'pending_approval' => 'منتظر تأیید',
            'overdue_dispatches' => 'معوق',
            'follow_up_due' => 'پیگیری',
            'unanswered_correspondence' => 'بی‌پاسخ',
        ] as $key => $label) {
            foreach (array_slice($queue[$key] ?? [], 0, 5) as $item) {
                $number = $item['registry_number'] ?? ('Draft #' . ($item['record_id'] ?? '?'));
                $title = trim((string) ($item['title'] ?? ''));
                $suffix = $title !== '' ? ' — ' . mb_substr($title, 0, 140) : '';
                $lines[] = "{$label}: {$number}{$suffix}";
            }
        }

        $lines[] = '';
        $lines[] = 'این گزارش فقط خواندنی است؛ نجم هدا هیچ تأیید، ارجاع، پاسخ یا تغییر وضعیتی را خودکار انجام نداده است.';

        return $this->response(implode("\n", $lines), 'work_queue', ['queue' => $queue, 'counts' => $counts]);
    }

    /** @param array<string,mixed> $pageContext */
    private function officeId(array $pageContext): int
    {
        if ((string) ($pageContext['resource_type'] ?? '') === 'secretariat_office') {
            return (int) ($pageContext['resource_id'] ?? 0);
        }
        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        return (int) ($resource['office_id'] ?? 0);
    }

    private function looksLikeWorkQueueRequest(string $message): bool
    {
        $plain = mb_strtolower($message);
        foreach (['کارهای باز', 'صف کار', 'معوق', 'پیگیری', 'منتظر تأیید', 'منتظر تایید', 'بی‌پاسخ', 'بی پاسخ', 'overdue', 'work queue', 'follow up'] as $needle) {
            if (mb_stripos($plain, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $extra */
    private function response(string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'success' => true,
            'message' => $message,
            'agent' => 'secretariat_work_queue',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '✦',
            'suggestions' => [],
            'grounded' => true,
            'status' => $status,
        ], $extra);
    }
}
