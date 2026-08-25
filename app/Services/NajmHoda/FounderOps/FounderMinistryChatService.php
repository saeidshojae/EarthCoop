<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderMinistryChatService
{
    public const INTENTS = [
        'morning_brief',
        'urgent_items',
        'pending_approvals',
        'communications',
        'system_health',
        'end_of_day',
    ];

    public function __construct(
        protected FounderAttentionService $attention,
        protected FounderExecutiveWorkQueueService $workQueue,
        protected FounderApprovalInboxService $approvals,
        protected FounderOperationsSnapshotService $snapshots,
    ) {}

    /** @return array<string,mixed> */
    public function respond(string $intent, int $hours = 24): array
    {
        $hours = max(1, min($hours, 168));

        if (! in_array($intent, self::INTENTS, true)) {
            return $this->response(
                false,
                'این درخواست در وزارت هوشمند ثبت نشده است.',
                $intent,
                [],
                [],
                ['reason' => 'unknown_management_intent']
            );
        }

        return match ($intent) {
            'morning_brief' => $this->morningBrief($hours),
            'urgent_items' => $this->urgentItems($hours),
            'pending_approvals' => $this->pendingApprovals(),
            'communications' => $this->communications($hours),
            'system_health' => $this->systemHealth($hours),
            'end_of_day' => $this->endOfDay($hours),
        };
    }

    /** @return array<string,mixed> */
    protected function morningBrief(int $hours): array
    {
        $brief = $this->attention->brief($hours);
        $queue = $this->workQueue->snapshot($hours, 50);
        $cards = $this->summaryCards($brief, $queue);
        $items = array_slice((array) data_get($queue, 'items', []), 0, 10);

        $message = sprintf(
            'صبح بخیر. در %d ساعت اخیر %d مورد فوری/مهم، %d تصمیم منتظر شما، %d کار آماده‌شده توسط نجم هدا و %d مورد صرفاً جهت اطلاع دارم. موارد زیر به ترتیب اولویت‌اند.',
            $hours,
            (int) $cards['urgent'],
            (int) $cards['founder_decisions'],
            (int) $cards['prepared'],
            (int) $cards['information']
        );

        return $this->response(true, $message, 'morning_brief', $cards, $items, [
            'generated_at' => data_get($brief, 'generated_at'),
            'window_hours' => $hours,
        ]);
    }

    /** @return array<string,mixed> */
    protected function urgentItems(int $hours): array
    {
        $queue = $this->workQueue->snapshot($hours, 100);
        $items = array_values(array_filter(
            (array) data_get($queue, 'items', []),
            static fn ($item): bool => is_array($item) && in_array((string) ($item['priority'] ?? ''), ['P0', 'P1'], true)
        ));

        return $this->response(
            true,
            count($items) > 0 ? 'این‌ها فوری‌ترین موضوعات مدیریتی فعلی هستند.' : 'در حال حاضر مورد P0 یا P1 ثبت نشده است.',
            'urgent_items',
            ['urgent' => count($items)],
            array_slice($items, 0, 20),
            ['window_hours' => $hours]
        );
    }

    /** @return array<string,mixed> */
    protected function pendingApprovals(): array
    {
        $approvals = $this->approvals->snapshot(100);
        $items = array_values(array_filter((array) data_get($approvals, 'items', []), 'is_array'));

        return $this->response(
            true,
            count($items) > 0 ? 'این تصمیم‌ها منتظر تأیید یا رد صریح شما هستند.' : 'در حال حاضر تصمیمی منتظر شما نیست.',
            'pending_approvals',
            [
                'pending' => (int) data_get($approvals, 'pending', count($items)),
                'overdue' => (int) data_get($approvals, 'overdue', 0),
            ],
            array_slice($items, 0, 30),
            ['by_risk' => (array) data_get($approvals, 'by_risk', [])]
        );
    }

    /** @return array<string,mixed> */
    protected function communications(int $hours): array
    {
        $queue = $this->workQueue->snapshot($hours, 100);
        $domains = ['support', 'email', 'blog', 'notifications'];
        $items = array_values(array_filter(
            (array) data_get($queue, 'items', []),
            static fn ($item): bool => is_array($item) && in_array((string) ($item['domain'] ?? ''), $domains, true)
        ));
        $approvals = count(array_filter($items, static fn (array $item): bool => ($item['kind'] ?? '') === 'approval'));
        $prepared = count(array_filter($items, static fn (array $item): bool => ($item['kind'] ?? '') === 'proposal'));

        return $this->response(
            true,
            count($items) > 0 ? 'وضعیت ارتباطات، پشتیبانی و انتشارهای آماده/منتظر تصمیم را جمع‌بندی کردم.' : 'مورد ارتباطی آماده یا منتظر تصمیم ثبت نشده است.',
            'communications',
            ['pending_decisions' => $approvals, 'prepared' => $prepared, 'total' => count($items)],
            array_slice($items, 0, 30),
            ['window_hours' => $hours]
        );
    }

    /** @return array<string,mixed> */
    protected function systemHealth(int $hours): array
    {
        $snapshot = $this->snapshots->snapshot($hours);
        $brief = $this->attention->brief($hours);
        $healthItems = array_values(array_filter(
            (array) data_get($brief, 'items', []),
            static fn ($item): bool => is_array($item) && in_array((string) ($item['domain'] ?? ''), ['runtime_health', 'financial_risk', 'stock', 'najm_bahar'], true)
        ));
        $runtimeStatus = (string) data_get($snapshot, 'runtime_health.status', 'unknown');

        return $this->response(
            true,
            $runtimeStatus === 'healthy'
                ? 'سلامت runtime نجم هدا در snapshot فعلی سالم گزارش شده است؛ هشدارهای مرتبط در کارت‌های زیر آمده‌اند.'
                : 'سلامت runtime نیازمند توجه است؛ جزئیات و هشدارهای مرتبط را در کارت‌های زیر ببینید.',
            'system_health',
            [
                'runtime_status' => $runtimeStatus,
                'health_attention_items' => count($healthItems),
            ],
            array_slice($healthItems, 0, 20),
            ['window_hours' => $hours]
        );
    }

    /** @return array<string,mixed> */
    protected function endOfDay(int $hours): array
    {
        $brief = $this->attention->brief($hours);
        $queue = $this->workQueue->snapshot($hours, 50);
        $cards = $this->summaryCards($brief, $queue);
        $items = array_slice((array) data_get($queue, 'items', []), 0, 12);

        $message = sprintf(
            'جمع‌بندی فعلی: %d تصمیم هنوز منتظر شماست، %d کار آماده بررسی است و %d مورد مدیریتی فقط برای اطلاع باقی مانده. این گزارش وضعیت اکنون است و ادعای انجام کاری خارج از رویدادهای ثبت‌شده ندارد.',
            (int) $cards['founder_decisions'],
            (int) $cards['prepared'],
            (int) $cards['information']
        );

        return $this->response(true, $message, 'end_of_day', $cards, $items, [
            'generated_at' => data_get($brief, 'generated_at'),
            'window_hours' => $hours,
        ]);
    }

    /** @return array<string,int> */
    protected function summaryCards(array $brief, array $queue): array
    {
        return [
            'urgent' => (int) data_get($brief, 'summary.P0', 0) + (int) data_get($brief, 'summary.P1', 0),
            'founder_decisions' => (int) data_get($queue, 'needs_founder_decision', 0),
            'prepared' => (int) data_get($queue, 'prepared_by_najm_hoda', 0),
            'information' => (int) data_get($queue, 'attention_only', 0),
        ];
    }

    /** @return array<string,mixed> */
    protected function response(bool $success, string $message, string $intent, array $cards, array $items, array $meta = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'agent' => 'steward',
            'agent_name' => 'نجم هدا — وزارت هوشمند',
            'agent_icon' => '✦',
            'suggestions' => [],
            'management' => [
                'intent' => $intent,
                'summary_cards' => $cards,
                'items' => $items,
                'meta' => $meta,
            ],
        ];
    }
}
