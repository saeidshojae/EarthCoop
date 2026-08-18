<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Deterministic private workflow for inspecting and managing a group's action queue.
 *
 * Read operations never need an LLM. Mutations are resolved server-side to one
 * concrete action item, previewed, cached as an exact payload, and persisted only
 * after explicit leadership confirmation.
 */
class NajmHodaPrivateGroupActionQueueService
{
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

        $pendingKey = $this->pendingKey($requester->id, $conversationId, $groupId);
        $pending = Cache::get($pendingKey);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($pendingKey);
            return $this->response('تغییر صف اقدام لغو شد و هیچ تغییری ثبت نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            Cache::forget($pendingKey);
            if (! $this->isLeadership($groupId, $requester->id)) {
                return $this->response('مجوز مدیریت صف اقدام این گروه برای نقش فعلی شما وجود ندارد.', 'blocked');
            }

            return $this->executePending($group, $requester, $pending);
        }

        if (! $this->isQueueRequest($message)) {
            return null;
        }

        if (! $this->isLeadership($groupId, $requester->id)) {
            return $this->response('مشاهده و مدیریت صف اقدام نجم هدا از این مسیر فقط برای مدیران و بازرسان گروه مجاز است.', 'blocked');
        }

        if ($this->looksLikeMutation($message)) {
            $plan = $this->planMutation($group, $message);
            if (! (bool) ($plan['ready'] ?? false)) {
                return $this->response((string) ($plan['message'] ?? 'برای این تغییر اطلاعات کافی ندارم.'), (string) ($plan['status'] ?? 'needs_input'));
            }

            Cache::put($pendingKey, [
                'group_id' => $groupId,
                'item_id' => (int) data_get($plan, 'payload.item_id', 0),
                'changes' => (array) data_get($plan, 'payload.changes', []),
            ], now()->addMinutes(15));

            return $this->response(
                (string) $plan['preview'] . "\n\nاگر همین تغییر باید اعمال شود «تأیید» بفرستید؛ برای انصراف «لغو» بفرستید. تا قبل از تأیید صف اقدام تغییر نمی‌کند.",
                'awaiting_confirmation'
            );
        }

        return $this->renderQueue($group, $message);
    }

    /** @return array<string,mixed> */
    protected function planMutation(Group $group, string $message): array
    {
        $target = $this->resolveTarget($group, $message);
        if (! (bool) ($target['resolved'] ?? false)) {
            return [
                'ready' => false,
                'status' => (string) ($target['status'] ?? 'needs_input'),
                'message' => (string) ($target['message'] ?? 'مورد اقدام موردنظر مشخص نیست.'),
            ];
        }

        /** @var NajmHodaGroupActionItem $item */
        $item = $target['item'];
        $changes = [];
        $plain = $this->normalize($message);

        if ($this->containsAny($plain, ['انجام شده کن', 'انجام‌شده کن', 'تکمیل کن', 'تمام شده کن', 'تمام‌شده کن', 'بسته شده کن', 'بسته‌شده کن'])) {
            $changes['status'] = 'done';
        } elseif ($this->containsAny($plain, ['لغو کن', 'کنسل کن', 'از صف خارج کن'])) {
            $changes['status'] = 'cancelled';
        } elseif ($this->containsAny($plain, ['در حال انجام کن', 'شروع شده کن', 'شروع‌شده کن'])) {
            $changes['status'] = 'in_progress';
        } elseif ($this->containsAny($plain, ['مسدود کن', 'متوقف کن', 'blocked'])) {
            $changes['status'] = 'blocked';
        } elseif ($this->containsAny($plain, ['باز کن', 'دوباره باز کن'])) {
            $changes['status'] = 'open';
        }

        $priority = $this->extractPriority($plain);
        if ($priority !== null) {
            $changes['priority'] = $priority;
        }

        $assignment = $this->extractAssignment($group, $message);
        if ((bool) ($assignment['mentioned'] ?? false)) {
            if (! (bool) ($assignment['resolved'] ?? false)) {
                return [
                    'ready' => false,
                    'status' => (string) ($assignment['status'] ?? 'needs_input'),
                    'message' => (string) ($assignment['message'] ?? 'مسئول موردنظر مشخص نشد.'),
                ];
            }
            $changes['assigned_user_id'] = $assignment['user_id'];
            $changes['assignee_name'] = $assignment['name'];
        }

        $due = $this->extractDueAt($message);
        if ((bool) ($due['mentioned'] ?? false)) {
            if (! (bool) ($due['resolved'] ?? false)) {
                return [
                    'ready' => false,
                    'status' => 'needs_input',
                    'message' => (string) ($due['message'] ?? 'موعد قابل تشخیص نیست.'),
                ];
            }
            $changes['due_at'] = $due['value'];
        }

        if ($changes === []) {
            return [
                'ready' => false,
                'status' => 'needs_input',
                'message' => "تغییر موردنظر مشخص نیست. مثال: «آخرین مورد اقدام را انجام‌شده کن»، «کار «عنوان» را به نام عضو بسپار» یا «کار «عنوان» | موعد: 2026-08-20 18:00».",
            ];
        }

        $lines = [
            'تغییر پیشنهادی در صف اقدام:',
            'مورد: ' . $item->title,
        ];
        if (isset($changes['status'])) $lines[] = 'وضعیت جدید: ' . $this->statusLabel((string) $changes['status']);
        if (isset($changes['priority'])) $lines[] = 'اولویت جدید: ' . $this->priorityLabel((string) $changes['priority']);
        if (array_key_exists('assigned_user_id', $changes)) $lines[] = 'مسئول جدید: ' . ((string) ($changes['assignee_name'] ?? '') ?: 'بدون مسئول');
        if (isset($changes['due_at'])) $lines[] = 'موعد جدید: ' . Carbon::parse((string) $changes['due_at'])->format('Y-m-d H:i');

        return [
            'ready' => true,
            'preview' => implode("\n", $lines),
            'payload' => [
                'item_id' => $item->id,
                'changes' => $changes,
            ],
        ];
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    protected function executePending(Group $group, User $requester, array $pending): array
    {
        $item = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->find((int) ($pending['item_id'] ?? 0));
        if (! $item) {
            return $this->response('مورد اقدام دیگر در این گروه پیدا نشد؛ تغییری اعمال نشد.', 'not_found');
        }

        $changes = is_array($pending['changes'] ?? null) ? $pending['changes'] : [];
        $allowed = ['status', 'priority', 'assigned_user_id', 'assignee_name', 'due_at'];
        $changes = array_intersect_key($changes, array_flip($allowed));
        if ($changes === []) {
            return $this->response('تغییر معتبری برای اعمال باقی نماند.', 'no_changes');
        }

        if (array_key_exists('assigned_user_id', $changes) && $changes['assigned_user_id'] !== null) {
            $member = GroupUser::query()
                ->where('group_id', $group->id)
                ->where('user_id', (int) $changes['assigned_user_id'])
                ->where('status', 1)
                ->exists();
            if (! $member) {
                return $this->response('مسئول انتخاب‌شده دیگر عضو فعال این گروه نیست؛ تغییری اعمال نشد.', 'blocked');
            }
        }

        $before = [
            'status' => $item->status,
            'priority' => $item->priority,
            'assigned_user_id' => $item->assigned_user_id,
            'assignee_name' => $item->assignee_name,
            'due_at' => optional($item->due_at)->toIso8601String(),
        ];

        foreach (['status', 'priority', 'assigned_user_id', 'due_at'] as $key) {
            if (array_key_exists($key, $changes)) $item->{$key} = $changes[$key];
        }
        if (array_key_exists('assignee_name', $changes)) $item->assignee_name = $changes['assignee_name'];

        $meta = is_array($item->meta) ? $item->meta : [];
        $history = is_array($meta['management_history'] ?? null) ? $meta['management_history'] : [];
        $history[] = [
            'changed_by_user_id' => $requester->id,
            'changed_at' => now()->toIso8601String(),
            'before' => $before,
            'changes' => $changes,
            'source' => 'najm_hoda_private_widget',
        ];
        $meta['management_history'] = array_slice($history, -50);
        $item->meta = $meta;
        $item->save();

        return $this->response("انجام شد. «{$item->title}» در صف اقدام به‌روزرسانی شد.", 'executed');
    }

    /** @return array<string,mixed> */
    protected function resolveTarget(Group $group, string $message): array
    {
        $plain = $this->normalize($message);

        if ($this->containsAny($plain, ['آخرین مورد', 'آخرین کار', 'آخرین اقدام'])) {
            $item = NajmHodaGroupActionItem::query()->where('group_id', $group->id)->latest('id')->first();
            return $item
                ? ['resolved' => true, 'item' => $item]
                : ['resolved' => false, 'status' => 'not_found', 'message' => 'هنوز موردی در صف اقدام این گروه ثبت نشده است.'];
        }

        $needle = null;
        if (preg_match('/[«"]([^»"]{2,255})[»"]/u', $message, $m) === 1) {
            $needle = trim($m[1]);
        } elseif (preg_match('/(?:مورد(?:\s+اقدام)?|کار|اقدام)\s+(.+?)\s+(?:را|رو)\s+/u', $message, $m) === 1) {
            $needle = trim($m[1]);
        }

        if ($needle === null || $needle === '') {
            return [
                'resolved' => false,
                'status' => 'needs_input',
                'message' => 'لطفاً مورد را با عنوانش مشخص کنید یا بگویید «آخرین مورد اقدام». لازم نیست شناسه داخلی بدانید.',
            ];
        }

        $matches = NajmHodaGroupActionItem::query()
            ->where('group_id', $group->id)
            ->where('title', 'like', '%' . $needle . '%')
            ->latest('id')
            ->take(6)
            ->get();

        if ($matches->count() === 1) {
            return ['resolved' => true, 'item' => $matches->first()];
        }
        if ($matches->isEmpty()) {
            return ['resolved' => false, 'status' => 'not_found', 'message' => "مورد اقدامی با عنوان نزدیک به «{$needle}» در این گروه پیدا نشد."];
        }

        $lines = ["چند مورد با «{$needle}» تطبیق دارند؛ برای جلوگیری از تغییر اشتباه، عنوان دقیق‌تر را بگویید:"];
        foreach ($matches as $match) $lines[] = '• ' . $match->title . ' — ' . $this->statusLabel((string) $match->status);
        return ['resolved' => false, 'status' => 'ambiguous', 'message' => implode("\n", $lines)];
    }

    /** @return array<string,mixed> */
    protected function extractAssignment(Group $group, string $message): array
    {
        $plain = $this->normalize($message);
        if ($this->containsAny($plain, ['بدون مسئول کن', 'مسئولش را بردار', 'مسئولش رو بردار'])) {
            return ['mentioned' => true, 'resolved' => true, 'user_id' => null, 'name' => null];
        }

        $name = null;
        if (preg_match('/(?:به|مسئول(?:ش)?\s*[:：]?)\s*([^|\n،,]{2,120}?)(?:\s+بسپار|\s+واگذار کن|\s+مسئول کن|\s*$)/u', $message, $m) === 1) {
            $name = trim($m[1]);
        }
        if ($name === null || $name === '') {
            return ['mentioned' => false];
        }

        $normalizedName = $this->normalize($name);
        $members = GroupUser::query()
            ->with('user:id,first_name,last_name,email')
            ->where('group_id', $group->id)
            ->where('status', 1)
            ->get()
            ->filter(function ($membership) use ($normalizedName): bool {
                $user = $membership->user;
                if (! $user) return false;
                $full = $this->normalize(trim((string) $user->first_name . ' ' . (string) $user->last_name));
                $email = $this->normalize((string) $user->email);
                return $full === $normalizedName || $email === $normalizedName || str_contains($full, $normalizedName);
            })
            ->values();

        if ($members->count() === 1) {
            $membership = $members->first();
            $user = $membership->user;
            $full = trim((string) $user->first_name . ' ' . (string) $user->last_name);
            return ['mentioned' => true, 'resolved' => true, 'user_id' => (int) $user->id, 'name' => $full !== '' ? $full : $user->email];
        }
        if ($members->isEmpty()) {
            return ['mentioned' => true, 'resolved' => false, 'status' => 'not_found', 'message' => "عضو فعال گروه با نام «{$name}» پیدا نشد."];
        }

        $names = $members->map(fn ($membership) => trim((string) $membership->user->first_name . ' ' . (string) $membership->user->last_name))->filter()->values()->all();
        return ['mentioned' => true, 'resolved' => false, 'status' => 'ambiguous', 'message' => 'چند عضو با این نام پیدا شدند: ' . implode('، ', $names) . '. نام کامل‌تر را بگویید.'];
    }

    /** @return array<string,mixed> */
    protected function extractDueAt(string $message): array
    {
        $plain = $this->normalize($message);
        if (preg_match('/موعد\s*[:：]\s*(\d{4}-\d{2}-\d{2})(?:[ T](\d{1,2}:\d{2}))?/u', $message, $m) === 1) {
            try {
                $value = Carbon::parse($m[1] . ' ' . ($m[2] ?? '23:59'));
                return ['mentioned' => true, 'resolved' => true, 'value' => $value->format('Y-m-d H:i:s')];
            } catch (\Throwable) {
                return ['mentioned' => true, 'resolved' => false, 'message' => 'فرمت موعد معتبر نیست. نمونه: موعد: 2026-08-20 18:00'];
            }
        }
        if (str_contains($plain, 'تا پس فردا') || str_contains($plain, 'موعد پس فردا')) {
            return ['mentioned' => true, 'resolved' => true, 'value' => now()->addDays(2)->endOfDay()->format('Y-m-d H:i:s')];
        }
        if (str_contains($plain, 'تا فردا') || str_contains($plain, 'موعد فردا')) {
            return ['mentioned' => true, 'resolved' => true, 'value' => now()->addDay()->endOfDay()->format('Y-m-d H:i:s')];
        }
        if ($this->containsAny($plain, ['موعد', 'سررسید'])) {
            return ['mentioned' => true, 'resolved' => false, 'message' => 'موعد را به صورت «موعد: YYYY-MM-DD HH:MM» یا «تا فردا/پس‌فردا» مشخص کنید.'];
        }
        return ['mentioned' => false];
    }

    protected function extractPriority(string $plain): ?string
    {
        if (! $this->containsAny($plain, ['اولویت', 'فوری', 'urgent'])) return null;
        if ($this->containsAny($plain, ['فوری', 'urgent'])) return 'urgent';
        if ($this->containsAny($plain, ['اولویت زیاد', 'اولویت بالا', 'high'])) return 'high';
        if ($this->containsAny($plain, ['اولویت کم', 'low'])) return 'low';
        if ($this->containsAny($plain, ['اولویت متوسط', 'medium'])) return 'medium';
        return null;
    }

    /** @return array<string,mixed> */
    protected function renderQueue(Group $group, string $message): array
    {
        $plain = $this->normalize($message);
        $query = NajmHodaGroupActionItem::query()->where('group_id', $group->id);
        $label = 'موارد فعال';

        if ($this->containsAny($plain, ['معوق', 'عقب افتاده', 'عقب‌افتاده'])) {
            $query->whereNotIn('status', ['done', 'cancelled'])->whereNotNull('due_at')->where('due_at', '<', now());
            $label = 'موارد معوق';
        } elseif ($this->containsAny($plain, ['بدون مسئول', 'بی مسئول', 'بی‌مسئول'])) {
            $query->whereNotIn('status', ['done', 'cancelled'])->whereNull('assigned_user_id')->where(function (Builder $q) {
                $q->whereNull('assignee_name')->orWhere('assignee_name', '');
            });
            $label = 'موارد بدون مسئول';
        } elseif ($this->containsAny($plain, ['اولویت بالا', 'اولویت زیاد', 'فوری'])) {
            $query->whereNotIn('status', ['done', 'cancelled'])->whereIn('priority', ['high', 'urgent']);
            $label = 'موارد با اولویت بالا';
        } elseif ($this->containsAny($plain, ['انجام شده', 'انجام‌شده', 'تکمیل شده'])) {
            $query->where('status', 'done');
            $label = 'موارد انجام‌شده';
        } elseif ($this->containsAny($plain, ['در حال انجام'])) {
            $query->where('status', 'in_progress');
            $label = 'موارد در حال انجام';
        } elseif ($this->containsAny($plain, ['لغو شده', 'لغوشده'])) {
            $query->where('status', 'cancelled');
            $label = 'موارد لغوشده';
        } else {
            $query->whereNotIn('status', ['done', 'cancelled']);
        }

        $items = $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->latest('id')
            ->take(12)
            ->get();

        $lines = ["{$label} صف اقدام «{$group->name}»:"];
        if ($items->isEmpty()) {
            $lines[] = '• موردی پیدا نشد.';
        } else {
            foreach ($items as $item) {
                $parts = [$this->statusLabel((string) $item->status), $this->priorityLabel((string) $item->priority)];
                if ($item->assignee_name) $parts[] = 'مسئول: ' . $item->assignee_name;
                if ($item->due_at) $parts[] = 'موعد: ' . $item->due_at->format('Y-m-d H:i');
                if ($item->due_at && $item->due_at->isPast() && ! in_array($item->status, ['done', 'cancelled'], true)) $parts[] = 'معوق';
                $lines[] = '• ' . $item->title . ' — ' . implode(' | ', $parts);
            }
        }
        $lines[] = '';
        $lines[] = 'برای تغییر لازم نیست شناسه بدانید؛ می‌توانید بگویید «آخرین مورد اقدام را انجام‌شده کن» یا «کار «عنوان» را به نام عضو بسپار».';

        return $this->response(implode("\n", $lines), 'listed');
    }

    protected function isQueueRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, [
            'صف اقدام', 'مورد اقدام', 'موارد اقدام', 'کارهای باز', 'کارهای گروه', 'کارهای معوق',
            'کارهای عقب افتاده', 'کارهای عقب‌افتاده', 'کارهای در حال انجام', 'وظایف گروه',
            'آخرین مورد', 'آخرین کار', 'آخرین اقدام',
        ]);
    }

    protected function looksLikeMutation(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, [
            'انجام شده کن', 'انجام‌شده کن', 'تکمیل کن', 'تمام شده کن', 'لغو کن', 'کنسل کن',
            'در حال انجام کن', 'شروع شده کن', 'مسدود کن', 'متوقف کن', 'دوباره باز کن',
            'بسپار', 'واگذار کن', 'مسئول کن', 'بدون مسئول کن', 'موعد', 'سررسید', 'اولویت',
        ]);
    }

    protected function isLeadership(int $groupId, int $userId): bool
    {
        $role = GroupUser::query()->where('group_id', $groupId)->where('user_id', $userId)->where('status', 1)->value('role');
        return in_array((int) $role, [2, 3], true);
    }

    protected function pendingKey(int $userId, ?int $conversationId, int $groupId): string
    {
        return 'najm_hoda:private_group_action_queue:' . $groupId . ':' . $userId . ':' . ($conversationId ?: 0);
    }

    protected function isConfirmation(string $message): bool
    {
        return in_array($this->normalize($message), ['تایید', 'تأیید', 'تایید کن', 'تأیید کن', 'confirm', 'yes'], true);
    }

    protected function isCancellation(string $message): bool
    {
        return in_array($this->normalize($message), ['لغو', 'لغو کن', 'cancel', 'نه', 'خیر'], true);
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
        foreach ($needles as $needle) if ($needle !== '' && mb_stripos($haystack, $needle) !== false) return true;
        return false;
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => 'در حال انجام', 'blocked' => 'مسدود', 'done' => 'انجام‌شده', 'cancelled' => 'لغوشده', default => 'باز',
        };
    }

    protected function priorityLabel(string $priority): string
    {
        return match ($priority) { 'urgent' => 'فوری', 'high' => 'زیاد', 'low' => 'کم', default => 'متوسط' };
    }

    /** @return array<string,mixed> */
    protected function response(string $message, string $status): array
    {
        return [
            'success' => true,
            'message' => $message,
            'agent' => 'runtime',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '📋',
            'suggestions' => [],
            'private_group_action_queue' => true,
            'status' => $status,
        ];
    }
}
