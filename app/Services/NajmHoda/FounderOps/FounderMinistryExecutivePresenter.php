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

    /** @return array<string,mixed> */
    public function present(array $response, int $hours): array
    {
        if (($response['success'] ?? false) !== true) {
            return $response;
        }

        $intent = (string) data_get($response, 'management.intent', '');
        $items = array_values(array_filter((array) data_get($response, 'management.items', []), 'is_array'));
        $global = (array) data_get($response, 'management.global_summary_cards', []);
        $summary = (array) data_get($response, 'management.summary_cards', []);

        $decisionCount = (int) ($global['founder_decisions'] ?? 0);
        $urgentCount = (int) ($global['urgent'] ?? 0);
        $preparedCount = (int) ($global['prepared'] ?? 0);
        $actionRequired = $decisionCount > 0 || $urgentCount > 0;

        $response['message'] = $this->executiveMessage($intent, $hours, $summary, $items, $global);
        $response['management']['executive'] = [
            'title' => self::TITLES[$intent] ?? 'گزارش مدیریتی',
            'window_hours' => $hours,
            'assessment' => $this->assessment($intent, $summary, $items, $global),
            'action_required' => $actionRequired,
            'action_text' => $this->actionText($decisionCount, $urgentCount, $preparedCount),
            'exception_driven' => $intent === 'morning_brief',
            'checked_without_action' => ! $actionRequired,
        ];

        return $response;
    }

    private function executiveMessage(string $intent, int $hours, array $summary, array $items, array $global): string
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
                'صبح بخیر. در %d ساعت اخیر %d موضوع نیازمند توجه شماست: %d مورد فوری/مهم و %d تصمیم منتظر شما. همچنین %d کار توسط نجم هدا آماده بررسی است. موارد استثنایی در ادامه به ترتیب اولویت آمده‌اند.',
                $hours,
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

        $facts = $this->facts($summary);
        $prefix = (self::TITLES[$intent] ?? 'این حوزه') . sprintf(' — %d ساعت اخیر: ', $hours);
        $factText = $facts === [] ? 'شاخص قابل‌نمایش دیگری ثبت نشده است.' : implode('، ', $facts) . '.';
        $relevantItems = count($items);

        if ($relevantItems > 0) {
            return $prefix . $factText . sprintf(' %d مورد برای رسیدگی/جزئیات در ادامه آمده است. اقدام شما: موارد کارت‌شده را بررسی کنید.', $relevantItems);
        }

        return $prefix . $factText . ' موردی برای رسیدگی در این بخش ثبت نشده است. اقدام شما: هیچ.';
    }

    private function assessment(string $intent, array $summary, array $items, array $global): string
    {
        $urgent = (int) ($global['urgent'] ?? 0);
        $decisions = (int) ($global['founder_decisions'] ?? 0);

        if ($urgent > 0) {
            return 'نیازمند توجه فوری مدیرکل';
        }
        if ($decisions > 0) {
            return 'نیازمند تصمیم مدیرکل';
        }
        if (count($items) > 0) {
            return 'مورد قابل بررسی وجود دارد، اما فوریت مدیریتی ثبت نشده است';
        }
        if ($intent === 'system_health' && (string) $this->rawMetric($summary, 'runtime_status') !== 'healthy') {
            return 'سلامت سامانه نیازمند بررسی است';
        }

        return 'در این حوزه اقدام مدیریتی فوری لازم نیست';
    }

    private function actionText(int $decisions, int $urgent, int $prepared): string
    {
        if ($urgent > 0) {
            return sprintf('%d مورد فوری را بررسی کنید%s.', $urgent, $decisions > 0 ? sprintf(' و درباره %d تصمیم منتظر نظر بدهید', $decisions) : '');
        }
        if ($decisions > 0) {
            return sprintf('درباره %d تصمیم منتظر نظر بدهید.', $decisions);
        }
        if ($prepared > 0) {
            return sprintf('%d کار آماده‌شده توسط نجم هدا برای بررسی اختیاری شما موجود است.', $prepared);
        }

        return 'فعلاً اقدامی از شما لازم نیست.';
    }

    /** @return array<int,string> */
    private function facts(array $summary): array
    {
        $facts = [];
        foreach ($summary as $key => $card) {
            if (is_array($card) && array_key_exists('value', $card)) {
                $label = trim((string) ($card['label'] ?? $key));
                $value = $card['value'];
            } elseif (is_scalar($card) || $card === null) {
                $label = (string) $key;
                $value = $card;
            } else {
                continue;
            }

            if ($label === '' || in_array($label, ['urgent', 'founder_decisions', 'prepared', 'information'], true)) {
                continue;
            }
            $facts[] = $label . ': ' . $this->displayValue($value);
        }

        return array_slice($facts, 0, 6);
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

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) return $value ? 'بله' : 'خیر';
        if ($value === null || $value === '') return '—';
        return (string) $value;
    }
}
