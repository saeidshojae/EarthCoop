<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class NajmHodaPrivateGroupMeetingCommandService
{
    public function __construct(protected NajmHodaGroupMeetingStewardService $meetings)
    {
    }

    /** @param array<string,mixed> $pageContext @return array<string,mixed>|null */
    public function intercept(User $requester, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if ((string) ($pageContext['page_kind'] ?? '') !== 'group_chat') {
            return null;
        }

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $groupId = (int) ($pageContext['resource_id'] ?? $resource['id'] ?? 0);
        if ($groupId <= 0) {
            return null;
        }

        $group = Group::query()->find($groupId);
        if (! $group) {
            return null;
        }

        $key = $this->pendingKey((int) $requester->id, $conversationId, $groupId);
        $pending = Cache::get($key);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($key);
            return $this->response('درخواست تنظیم نشست لغو شد و هیچ نشستی ایجاد نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            Cache::forget($key);
            $result = $this->meetings->executeConfirmed(
                $requester,
                $group,
                is_array($pending['payload'] ?? null) ? $pending['payload'] : []
            );

            return $this->executionResponse($result);
        }

        if ($this->looksLikeStatusRequest($message)) {
            $state = $this->meetings->currentState($group);
            $lines = ["وضعیت نشست‌های رسمی «{$group->name}»:" ];
            $active = $state['active'] ?? null;
            if (is_array($active)) {
                $lines[] = '• نشست فعال: ' . ((string) ($active['title'] ?? 'نشست رسمی'));
                if (! empty($active['started_at'])) $lines[] = '• آغاز: ' . $active['started_at'];
            } else {
                $lines[] = '• نشست فعال: ندارد';
            }

            $scheduled = array_values((array) ($state['scheduled'] ?? []));
            $lines[] = '• نشست‌های برنامه‌ریزی‌شده: ' . count($scheduled);
            foreach (array_slice($scheduled, 0, 5) as $session) {
                if (! is_array($session)) continue;
                $lines[] = '- ' . ((string) ($session['title'] ?? 'نشست رسمی')) . ' | ' . ((string) ($session['starts_at'] ?? '-'));
            }

            return $this->response(implode("\n", $lines), 'meeting_state');
        }

        if (! $this->looksLikeMeetingRequest($message)) {
            return null;
        }

        $input = $this->parseRequest($message);
        $preview = $this->meetings->preview($requester, $group, $input);

        if (! (bool) ($preview['allowed'] ?? false)) {
            return $this->response((string) ($preview['message'] ?? 'مجوز مدیریت نشست را ندارید.'), 'blocked');
        }

        if ((bool) ($preview['needs_input'] ?? false)) {
            return $this->response((string) ($preview['message'] ?? 'اطلاعات نشست کامل نیست.'), 'needs_input');
        }

        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];
        Cache::put($key, [
            'group_id' => $groupId,
            'requester_user_id' => (int) $requester->id,
            'payload' => $payload,
        ], now()->addMinutes(20));

        $text = (string) ($preview['preview'] ?? 'نشست آماده ثبت است.');
        return $this->response(
            "درخواست تنظیم نشست آماده شد:\n\n{$text}\n\nبرای ثبت همین نشست «تأیید» بفرستید؛ برای انصراف «لغو» بفرستید. تا قبل از تأیید هیچ نشستی ساخته نمی‌شود.",
            'awaiting_confirmation'
        );
    }

    /** @return array<string,mixed> */
    protected function parseRequest(string $message): array
    {
        $plain = trim(strip_tags($message));
        $startsAt = null;
        if (preg_match('/(?:زمان|تاریخ|ساعت)\s*[:：]\s*([^|\n]+)/u', $plain, $m)) {
            $startsAt = trim($m[1]);
        } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2})\b/u', $plain, $m)) {
            $startsAt = trim($m[1]);
        } elseif (preg_match('/(?:الان|اکنون|همین الان|همین‌الان)/u', $plain) === 1) {
            $startsAt = 'الان';
        }

        return [
            'title' => $this->extract($plain, ['عنوان'], ['موضوع', 'دستور جلسه', 'دستورجلسه', 'زمان', 'تاریخ', 'ساعت']),
            'subject' => $this->extract($plain, ['موضوع'], ['دستور جلسه', 'دستورجلسه', 'زمان', 'تاریخ', 'ساعت']),
            'agenda' => $this->extract($plain, ['دستور جلسه', 'دستورجلسه'], ['زمان', 'تاریخ', 'ساعت']),
            'starts_at' => $startsAt,
        ];
    }

    protected function extract(string $text, array $labels, array $stops): string
    {
        $labelPattern = implode('|', array_map(static fn (string $v): string => preg_quote($v, '/'), $labels));
        $stopPattern = implode('|', array_map(static fn (string $v): string => preg_quote($v, '/'), $stops));
        $pattern = '/(?:' . $labelPattern . ')\s*[:：]\s*(.+?)';
        if ($stopPattern !== '') {
            $pattern .= '(?=\s*(?:\||\n)\s*(?:' . $stopPattern . ')\s*[:：]|$)';
        } else {
            $pattern .= '$';
        }
        $pattern .= '/us';
        return preg_match($pattern, $text, $m) === 1 ? trim((string) ($m[1] ?? '')) : '';
    }

    protected function looksLikeMeetingRequest(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        $nouns = ['نشست', 'جلسه رسمی', 'جلسه مدیریتی'];
        $verbs = ['تنظیم کن', 'برگزار کن', 'برنامه ریزی کن', 'برنامه‌ریزی کن', 'بذار', 'بگذار', 'بساز', 'شروع کن'];
        return $this->containsAny($plain, $nouns) && $this->containsAny($plain, $verbs);
    }

    protected function looksLikeStatusRequest(string $message): bool
    {
        $plain = mb_strtolower(trim(strip_tags($message)));
        return $this->containsAny($plain, ['نشست', 'جلسه رسمی', 'جلسه مدیریتی'])
            && $this->containsAny($plain, ['وضعیت', 'چه جلسه', 'چه نشست', 'برنامه ریزی شده', 'برنامه‌ریزی شده', 'فعال']);
    }

    protected function containsAny(string $plain, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($plain, $needle) !== false) return true;
        }
        return false;
    }

    protected function isConfirmation(string $message): bool
    {
        return in_array(mb_strtolower(trim(strip_tags($message))), [
            'تایید','تأیید','تایید کن','تأیید کن','بله','بله تایید','بله تأیید','انجام بده','اجرا کن','اوکی','ok','yes','confirm'
        ], true);
    }

    protected function isCancellation(string $message): bool
    {
        return in_array(mb_strtolower(trim(strip_tags($message))), ['لغو','لغو کن','بیخیال','بی‌خیال','انصراف','cancel','no'], true);
    }

    protected function pendingKey(int $userId, ?int $conversationId, int $groupId): string
    {
        $conversation = $conversationId && $conversationId > 0 ? $conversationId : 0;
        return "najm_hoda:private_group_meeting:user:{$userId}:conversation:{$conversation}:group:{$groupId}";
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    protected function executionResponse(array $result): array
    {
        $decision = (string) ($result['decision'] ?? 'failed');
        $detail = trim((string) ($result['group_reply'] ?? ''));
        if ($decision === 'executed') {
            return $this->response($detail !== '' ? $detail : 'نشست رسمی ثبت شد.', 'executed');
        }
        return $this->response($detail !== '' ? $detail : 'نشست ثبت نشد.', $decision ?: 'failed');
    }

    /** @return array<string,mixed> */
    protected function response(string $message, string $status): array
    {
        return [
            'success' => true,
            'message' => $message,
            'agent' => 'runtime',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '🗓️',
            'suggestions' => [],
            'private_group_meeting' => true,
            'action_status' => $status,
        ];
    }
}
