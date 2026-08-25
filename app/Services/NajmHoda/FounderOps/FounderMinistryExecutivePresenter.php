<?php

namespace App\Services\NajmHoda\FounderOps;

class FounderMinistryExecutivePresenter
{
    private const TITLES = [
        'morning_brief' => 'صبح مدیرکل',
        'urgent_items' => 'کارهای فوری',
        'pending_approvals' => 'تصمیم‌های منتظر مدیرکل',
        'communications' => 'ارتباطات',
        'system_health' => 'سلامت سامانه',
        'end_of_day' => 'پایان روز مدیرکل',
        'users_registration' => 'کاربران و ثبت‌نام',
        'reference_data' => 'مکان، صنف و تخصص',
        'support_moderation' => 'پشتیبانی و شکایات',
        'groups' => 'گروه‌ها',
        'governance' => 'انتخابات و حکمرانی',
        'najm_bahar' => 'نجم بهار',
        'stock' => 'سهام و تأمین مالی',
        'secretariat' => 'دبیرخانه',
        'authority' => 'اختیارها و واگذاری‌ها',
    ];

    public function present(array $response, int $hours, ?array $agency = null): array
    {
        if (($response['success'] ?? false) !== true) {
            return $response;
        }

        $intent = (string) data_get($response, 'management.intent', '');
        $items = array_values(array_filter((array) data_get($response, 'management.items', []), 'is_array'));

        if ($intent === 'morning_brief') {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => in_array((string) ($item['priority'] ?? 'P3'), ['P0', 'P1'], true)
                    || in_array((string) ($item['kind'] ?? ''), ['approval', 'proposal'], true)
            ));
            $response['management']['items'] = $items;
        }

        $global = (array) data_get($response, 'management.global_summary_cards', []);
        $summary = (array) data_get($response, 'management.summary_cards', []);
        [$urgent, $decisions, $prepared] = $this->scopedCounts($intent, $items, $global);
        $domainNeeds = $this->domainNeedsAction($intent, $summary, $items);
        $actionRequired = $urgent > 0 || $decisions > 0 || $domainNeeds;

        $response['message'] = $this->executiveMessage($intent, $hours, $summary, $items, $global, $domainNeeds);
        $response['management']['executive'] = [
            'title' => self::TITLES[$intent] ?? 'گزارش مدیریتی',
            'window_hours' => $hours,
            'assessment' => $this->assessment($intent, $summary, $items, $urgent, $decisions, $domainNeeds),
            'action_required' => $actionRequired,
            'action_text' => $this->actionText($intent, $summary, $decisions, $urgent, $prepared, $domainNeeds),
            'exception_driven' => $intent === 'morning_brief',
            'checked_without_action' => ! $actionRequired,
            'scope' => $this->usesGlobalScope($intent) ? 'global' : 'intent',
        ];
        if ($agency !== null) {
            $response['management']['executive']['agency'] = $agency;
        }

        return $response;
    }

    private function executiveMessage(string $intent, int $hours, array $summary, array $items, array $global, bool $domainNeeds): string
    {
        if ($intent === 'morning_brief') {
            $urgent = (int) ($global['urgent'] ?? 0);
            $decisions = (int) ($global['founder_decisions'] ?? 0);
            $prepared = (int) ($global['prepared'] ?? 0);
            $information = (int) ($global['information'] ?? 0);
            $attention = $urgent + $decisions;

            if ($attention === 0 && $prepared === 0) {
                return sprintf(
                    'صبح بخیر. وضعیت مدیریتی EarthCoop در %d ساعت اخیر پایدار است. موضوع فوری یا تصمیم منتظر شما ندارم؛ %d مورد صرفاً جهت اطلاع ثبت شده است. اقدام شما: فعلاً هیچ.',
                    $hours,
                    $information
                );
            }

            return sprintf(
                'صبح بخیر. در %d ساعت اخیر %d موضوع نیازمند توجه شماست: %d مورد فوری/مهم و %d تصمیم منتظر شما. همچنین %d کار توسط نجم هدا آماده بررسی است. فقط موارد استثنایی در ادامه آمده‌اند.',
                $hours,
                $attention,
                $urgent,
                $decisions,
                $prepared
            );
        }

        if ($intent === 'end_of_day') {
            $urgent = (int) ($global['urgent'] ?? 0);
            $decisions = (int) ($global['founder_decisions'] ?? 0);
            $prepared = (int) ($global['prepared'] ?? 0);
            $information = (int) ($global['information'] ?? 0);
            $attention = $urgent + $decisions;

            if ($attention === 0) {
                return sprintf(
                    'پایان روز مدیرکل — در %d ساعت اخیر کار فوری یا تصمیم منتظر شما باقی نمانده است. %d کار آماده بررسی و %d مورد صرفاً جهت اطلاع ثبت شده است. اقدام شما: هیچ اقدام اجباری باقی نمانده است.',
                    $hours,
                    $prepared,
                    $information
                );
            }

            return sprintf(
                'پایان روز مدیرکل — هنوز %d موضوع نیازمند توجه شماست: %d مورد فوری/مهم و %d تصمیم منتظر. %d کار نیز آماده بررسی است. اقدام شما: موارد باز را پیش از بستن چرخه مدیریتی بررسی کنید.',
                $attention,
                $urgent,
                $decisions,
                $prepared
            );
        }

        if ($intent === 'pending_approvals') {
            $pending = $this->metric($summary, 'pending');
            return $pending > 0
                ? sprintf('%d تصمیم منتظر تأیید یا رد صریح شماست. اقدام شما: کارت‌های زیر را بررسی و درباره هر مورد تصمیم بگیرید.', $pending)
                : 'در حال حاضر تصمیمی منتظر شما نیست. اقدام شما: هیچ.';
        }

        if ($intent === 'urgent_items') {
            return count($items) > 0
                ? sprintf('%d موضوع فوری/مهم پیدا کردم. اقدام شما: موارد زیر را به ترتیب اولویت بررسی کنید.', count($items))
                : 'در حال حاضر موضوع P0 یا P1 ثبت نشده است. اقدام شما: هیچ.';
        }

        if ($intent === 'authority') {
            $active = $this->metric($summary, 'active_delegations');
            $total = $this->metric($summary, 'total_actions');
            $pending = $this->metric($summary, 'pending_approvals');
            $overdue = $this->metric($summary, 'overdue_approvals');

            return sprintf(
                'اختیارها و واگذاری‌ها — %d اقدام در ماتریس اختیار تعریف شده است؛ این عدد به معنی واگذاری فعال نیست. اکنون %d واگذاری فعال، %d تأیید منتظر و %d تأیید عقب‌افتاده داریم. اقدام شما: %s',
                $total,
                $active,
                $pending,
                $overdue,
                ($pending + $overdue) > 0 ? 'تأییدهای منتظر/عقب‌افتاده را بررسی کنید.' : 'هیچ.'
            );
        }

        if ($intent === 'system_health') {
            $runtime = (string) $this->rawMetric($summary, 'runtime_status');
            $attentionItems = $this->metric($summary, 'health_attention_items');
            if ($runtime === 'healthy' && $attentionItems === 0) {
                return sprintf('سلامت سامانه — %d ساعت اخیر: runtime سالم است و هشدار سلامت نیازمند پیگیری ثبت نشده است. اقدام شما: هیچ.', $hours);
            }

            return sprintf(
                'سلامت سامانه نیازمند بررسی است. در %d ساعت اخیر وضعیت runtime «%s» و تعداد هشدارهای مرتبط %d است. اقدام شما: کارت‌های سلامت و ریسک را بررسی کنید.',
                $hours,
                $runtime !== '' ? $runtime : 'نامشخص',
                $attentionItems
            );
        }

        $title = self::TITLES[$intent] ?? 'این حوزه';
        $conclusion = $domainNeeds
            ? $title.' نیازمند پیگیری مدیرکل است.'
            : $title.' در وضعیت فعلی نیازمند اقدام شما نیست.';
        $relevant = count($items);

        return $conclusion.($relevant > 0
            ? sprintf(' %d مورد مرتبط برای رسیدگی/جزئیات در ادامه آمده است.', $relevant)
            : ' مورد کارت‌شده‌ای برای رسیدگی در این بخش وجود ندارد.');
    }

    private function assessment(string $intent, array $summary, array $items, int $urgent, int $decisions, bool $domainNeeds): string
    {
        if ($intent === 'system_health' && (string) $this->rawMetric($summary, 'runtime_status') !== 'healthy') {
            return 'سلامت سامانه نیازمند بررسی است';
        }
        if ($urgent > 0) {
            return 'نیازمند توجه فوری مدیرکل';
        }
        if ($decisions > 0 || $domainNeeds) {
            return 'نیازمند تصمیم یا پیگیری مدیرکل';
        }
        if (count($items) > 0) {
            return 'مورد قابل بررسی وجود دارد، اما فوریت مدیریتی ثبت نشده است';
        }

        return 'در این حوزه اقدام مدیریتی فوری لازم نیست';
    }

    private function actionText(string $intent, array $summary, int $decisions, int $urgent, int $prepared, bool $domainNeeds): string
    {
        if ($urgent > 0) {
            return sprintf('%d مورد فوری را بررسی کنید%s.', $urgent, $decisions > 0 ? sprintf(' و درباره %d تصمیم منتظر نظر بدهید', $decisions) : '');
        }
        if ($decisions > 0) {
            return sprintf('درباره %d تصمیم منتظر نظر بدهید.', $decisions);
        }
        if ($domainNeeds) {
            return match ($intent) {
                'reference_data' => 'داده‌های پایه منتظر را بررسی و تأیید/رد کنید.',
                'support_moderation' => 'تیکت‌های فوری یا گزارش‌های ارجاع‌شده را بررسی کنید.',
                'governance' => 'انتخابات عقب‌افتاده را بررسی کنید.',
                'najm_bahar' => 'تراکنش‌های عقب‌افتاده یا موارد نیازمند اصلاح را بررسی کنید.',
                'stock' => 'تطبیق، تسویه یا پرداخت‌های مسئله‌دار را بررسی کنید.',
                'secretariat' => 'ارسال‌های عقب‌افتاده و پاسخ‌های منتظر را پیگیری کنید.',
                'authority' => 'تأییدهای منتظر یا عقب‌افتاده را بررسی کنید.',
                'system_health' => 'کارت‌های سلامت runtime و ریسک‌های مرتبط را بررسی کنید.',
                default => 'موارد نیازمند رسیدگی را بررسی کنید.',
            };
        }
        if ($prepared > 0) {
            return sprintf('%d کار آماده‌شده توسط نجم هدا برای بررسی اختیاری شما موجود است.', $prepared);
        }

        return 'فعلاً اقدامی از شما لازم نیست.';
    }

    private function domainNeedsAction(string $intent, array $summary, array $items): bool
    {
        if (count(array_filter(
            $items,
            static fn (array $item): bool => in_array((string) ($item['priority'] ?? ''), ['P0', 'P1'], true)
                || ($item['kind'] ?? '') === 'approval'
        )) > 0) {
            return true;
        }

        return match ($intent) {
            'reference_data' => $this->metric($summary, 'pending') > 0,
            'support_moderation' => $this->metric($summary, 'high_priority') > 0 || $this->metric($summary, 'escalated_reports') > 0,
            'governance' => $this->metric($summary, 'overdue') > 0,
            'najm_bahar' => $this->metric($summary, 'scheduled_overdue') > 0,
            'stock' => $this->metric($summary, 'reconciliation') > 0 || $this->metric($summary, 'expired_unsettled') > 0 || $this->metric($summary, 'failed_payments') > 0,
            'secretariat' => $this->metric($summary, 'overdue_dispatches') > 0 || $this->metric($summary, 'responses_due') > 0,
            'authority' => $this->metric($summary, 'pending_approvals') > 0 || $this->metric($summary, 'overdue_approvals') > 0,
            'system_health' => (string) $this->rawMetric($summary, 'runtime_status') !== 'healthy' || $this->metric($summary, 'health_attention_items') > 0,
            default => false,
        };
    }

    /** @return array{0:int,1:int,2:int} */
    private function scopedCounts(string $intent, array $items, array $global): array
    {
        if ($this->usesGlobalScope($intent)) {
            return [
                (int) ($global['urgent'] ?? 0),
                (int) ($global['founder_decisions'] ?? 0),
                (int) ($global['prepared'] ?? 0),
            ];
        }

        $urgent = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['kind'] ?? '') !== 'approval'
                && in_array((string) ($item['priority'] ?? ''), ['P0', 'P1'], true)
        ));
        $decisions = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['kind'] ?? '') === 'approval'
        ));
        $prepared = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['kind'] ?? '') === 'proposal'
        ));

        return [$urgent, $decisions, $prepared];
    }

    private function usesGlobalScope(string $intent): bool
    {
        return in_array($intent, ['morning_brief', 'end_of_day'], true);
    }

    private function metric(array $summary, string $key): int
    {
        return (int) $this->rawMetric($summary, $key);
    }

    private function rawMetric(array $summary, string $key): mixed
    {
        $value = $summary[$key] ?? 0;
        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    }
}
