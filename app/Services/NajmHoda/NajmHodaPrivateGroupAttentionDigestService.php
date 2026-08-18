<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deterministic attention digest for a group's action queue.
 *
 * This service never uses an LLM and never mutates the queue. It ranks existing
 * action items by operational risk so a group leader can ask a natural question
 * such as «الان چه چیزهایی نیاز به توجه من دارد؟» without knowing queue filters.
 */
class NajmHodaPrivateGroupAttentionDigestService
{
    /** @param array<string,mixed> $pageContext @return array<string,mixed>|null */
    public function intercept(User $requester, array $pageContext, string $message): ?array
    {
        if (! $this->looksLikeAttentionRequest($message)) {
            return null;
        }

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

        if (! $this->isLeadership($groupId, $requester->id)) {
            return $this->response('گزارش مدیریتی صف اقدام این گروه فقط برای مدیران و بازرسان فعال گروه قابل مشاهده است.', 'blocked');
        }

        return $this->renderDigest($group);
    }

    /** @return array<string,mixed> */
    protected function renderDigest(Group $group): array
    {
        $now = now();
        $nearDeadline = $now->copy()->addHours(48);

        /** @var Collection<int,NajmHodaGroupActionItem> $active */
        $active = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->get();

        $classified = $active->map(function (NajmHodaGroupActionItem $item) use ($now, $nearDeadline): array {
            $reasons = [];
            $score = 0;

            if ($item->due_at && $item->due_at->lt($now)) {
                $reasons[] = 'معوق';
                $score += 100;
            }
            if ((string) $item->status === 'blocked') {
                $reasons[] = 'مسدود';
                $score += 80;
            }
            if ((string) $item->priority === 'urgent') {
                $reasons[] = 'فوری';
                $score += 60;
            }
            if ($item->due_at && $item->due_at->gte($now) && $item->due_at->lte($nearDeadline)) {
                $hours = max(1, (int) ceil($now->diffInMinutes($item->due_at) / 60));
                $reasons[] = "نزدیک موعد ({$hours} ساعت)";
                $score += 45;
            }
            if (! $item->assigned_user_id && trim((string) $item->assignee_name) === '') {
                $reasons[] = 'بدون مسئول';
                $score += 30;
            }
            if ((string) $item->priority === 'high') {
                $reasons[] = 'اولویت بالا';
                $score += 20;
            }

            return ['item' => $item, 'reasons' => $reasons, 'score' => $score];
        });

        $attention = $classified
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortByDesc('score')
            ->values();

        $counts = [
            'active' => $active->count(),
            'overdue' => $classified->filter(fn (array $r) => in_array('معوق', $r['reasons'], true))->count(),
            'blocked' => $classified->filter(fn (array $r) => in_array('مسدود', $r['reasons'], true))->count(),
            'urgent' => $classified->filter(fn (array $r) => in_array('فوری', $r['reasons'], true))->count(),
            'near_due' => $classified->filter(fn (array $r) => collect($r['reasons'])->contains(fn ($reason) => str_starts_with((string) $reason, 'نزدیک موعد')))->count(),
            'unassigned' => $classified->filter(fn (array $r) => in_array('بدون مسئول', $r['reasons'], true))->count(),
        ];

        $lines = [
            "گزارش توجه مدیریتی — «{$group->name}»",
            '',
            "• فعال: {$counts['active']} | معوق: {$counts['overdue']} | مسدود: {$counts['blocked']} | فوری: {$counts['urgent']} | نزدیک موعد: {$counts['near_due']} | بدون مسئول: {$counts['unassigned']}",
            '',
        ];

        if ($attention->isEmpty()) {
            $lines[] = 'در حال حاضر مورد فعالی که بر اساس موعد، وضعیت، اولویت یا مسئول نیازمند توجه ویژه باشد پیدا نشد.';
        } else {
            $lines[] = 'مواردی که اکنون بیشتر نیاز به توجه دارند:';
            foreach ($attention->take(10) as $row) {
                /** @var NajmHodaGroupActionItem $item */
                $item = $row['item'];
                $parts = $row['reasons'];
                if (trim((string) $item->assignee_name) !== '') {
                    $parts[] = 'مسئول: ' . $item->assignee_name;
                }
                if ($item->due_at) {
                    $parts[] = 'موعد: ' . $item->due_at->format('Y-m-d H:i');
                }
                $lines[] = '• ' . $item->title . ' — ' . implode(' | ', $parts);
            }
        }

        $lines[] = '';
        $lines[] = 'برای اقدام لازم نیست شناسه‌ای بدانید؛ مثلاً بگویید «کار «عنوان» را به نام عضو بسپار» یا «کار «عنوان» را انجام‌شده کن».';

        return $this->response(implode("\n", $lines), 'attention_digest');
    }

    protected function looksLikeAttentionRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, [
            'نیاز به توجه من', 'نیاز به توجه', 'توجه مدیریتی', 'گزارش مدیریتی صف اقدام',
            'چه چیزهایی مهمه', 'چه چیزهایی مهم است', 'چی مهمه', 'چی مهم است',
            'چه کارهایی فوریه', 'چه کارهایی فوری است', 'الان باید به چی برسم',
            'الان باید به چه چیزی برسم', 'چه چیزی نیاز به پیگیری دارد', 'چه چیزهایی نیاز به پیگیری دارند',
            'وضعیت بحرانی', 'وضعیت فوری گروه', 'اولویت های من', 'اولویت‌های من',
        ]);
    }

    protected function isLeadership(int $groupId, int $userId): bool
    {
        $role = GroupUser::query()
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->value('role');

        return in_array((int) $role, [2, 3], true);
    }

    protected function normalize(string $value): string
    {
        $plain = mb_strtolower(trim(strip_tags($value)));
        $plain = str_replace(['ي', 'ك', 'ۀ'], ['ی', 'ک', 'ه'], $plain);
        return preg_replace('/\s+/u', ' ', $plain) ?: $plain;
    }

    /** @param array<int,string> $needles */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed> */
    protected function response(string $message, string $status): array
    {
        return [
            'success' => true,
            'message' => $message,
            'agent' => 'runtime',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '🧭',
            'suggestions' => [],
            'private_group_attention_digest' => true,
            'status' => $status,
        ];
    }
}
