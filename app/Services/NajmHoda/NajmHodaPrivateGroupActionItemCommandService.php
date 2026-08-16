<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Private manager workflow for semantic action-item proposals.
 *
 * Extraction is read-only. Nothing enters the action queue until a permitted
 * group leader confirms the exact structured proposal shown in the widget.
 */
class NajmHodaPrivateGroupActionItemCommandService
{
    public function __construct(protected NajmHodaGroupActionCandidateService $extractor)
    {
    }

    /** @param array<string,mixed> $pageContext @return array<string,mixed>|null */
    public function intercept(User $requester, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        // Existing queue items are managed by a deterministic, no-LLM service.
        // Run it before semantic extraction so confirmations and ordinary queue
        // queries are never mistaken for a new extraction request.
        $queueResponse = app(NajmHodaPrivateGroupActionQueueService::class)->intercept(
            $requester,
            $pageContext,
            $message,
            $conversationId
        );
        if (is_array($queueResponse)) {
            return $queueResponse;
        }

        if ((string) ($pageContext['page_kind'] ?? '') !== 'group_chat') return null;

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $groupId = (int) ($pageContext['resource_id'] ?? $resource['id'] ?? 0);
        if ($groupId <= 0) return null;

        $group = Group::query()->find($groupId);
        if (! $group) return null;

        $key = $this->pendingKey($requester->id, $conversationId, $groupId);
        $pending = Cache::get($key);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($key);
            return $this->response('پیشنهادهای Action Item لغو شدند و چیزی در صف اقدام ثبت نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            if (! $this->isLeadership($groupId, $requester->id)) {
                Cache::forget($key);
                return $this->response('مجوز ثبت Action Item در این گروه برای نقش فعلی شما وجود ندارد.', 'blocked');
            }

            Cache::forget($key);
            $created = $this->persistConfirmed($group, $requester, (array) ($pending['candidates'] ?? []));
            return $this->response(
                $created === 0
                    ? 'مورد معتبری برای ثبت در صف اقدام باقی نماند.'
                    : "انجام شد. {$created} مورد تأییدشده در صف اقدام گروه ثبت شد.",
                'executed'
            );
        }

        if (! $this->looksLikeExtractionRequest($message)) return null;

        if (! $this->isLeadership($groupId, $requester->id)) {
            return $this->response('استخراج و ثبت پیشنهادهای Action Item از طریق نجم هدا فقط برای مدیران/بازرسان مجاز است.', 'blocked');
        }

        [$from, $to, $label] = $this->resolveWindow($message);
        $result = $this->extractor->extract($group, $from, $to);
        if (! (bool) ($result['available'] ?? false)) {
            return $this->response(
                'تحلیل معنایی برای استخراج Action Item در حال حاضر در دسترس نیست. برای جلوگیری از حدس، هیچ موردی پیشنهاد یا ثبت نشد.',
                'provider_unavailable'
            );
        }

        $candidates = array_values((array) ($result['candidates'] ?? []));
        if ($candidates === []) {
            return $this->response("در محتوای {$label} مورد اقدام قابل اتکایی با evidence مستقیم پیدا نشد؛ چیزی برای ثبت پیشنهاد نمی‌کنم.", 'no_candidates');
        }

        Cache::put($key, [
            'group_id' => $groupId,
            'requester_user_id' => $requester->id,
            'candidates' => $candidates,
        ], now()->addMinutes(20));

        $lines = [
            "پیشنهادهای Action Item برای «{$group->name}» — {$label}",
            '',
            'این موارد هنوز مصوبه یا وظیفه قطعی نیستند:',
        ];
        foreach ($candidates as $index => $candidate) {
            $number = $index + 1;
            $lines[] = '';
            $lines[] = "{$number}) " . (string) ($candidate['title'] ?? 'مورد اقدام');
            $lines[] = 'شرح: ' . (string) ($candidate['details'] ?? '');
            $lines[] = 'اولویت پیشنهادی: ' . $this->priorityLabel((string) ($candidate['priority'] ?? 'medium'));
            if (! empty($candidate['assignee_name'])) $lines[] = 'مسئول پیشنهادی: ' . $candidate['assignee_name'];
            if (! empty($candidate['due_text'])) $lines[] = 'موعد پیشنهادی: ' . $candidate['due_text'];
            $lines[] = 'شاهد: «' . (string) ($candidate['evidence'] ?? '') . '» (' . $this->sourceLabel((string) ($candidate['source'] ?? '')) . ')';
        }
        $lines[] = '';
        $lines[] = 'برای ثبت همین فهرست در صف اقدام «تأیید» بفرستید؛ برای انصراف «لغو» بفرستید. تا قبل از تأیید هیچ Action Itemی ساخته نمی‌شود.';

        return $this->response(implode("\n", $lines), 'awaiting_confirmation');
    }

    /** @param array<int,array<string,mixed>> $candidates */
    protected function persistConfirmed(Group $group, User $requester, array $candidates): int
    {
        $created = 0;
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) continue;

            $source = (string) ($candidate['source'] ?? '');
            $sourceMessageId = null;
            if (preg_match('/^message:(\d+)$/', $source, $m) === 1) {
                $sourceMessageId = (int) $m[1];
            }

            NajmHodaGroupActionItem::create([
                'group_id' => $group->id,
                'source_message_id' => $sourceMessageId,
                'assigned_user_id' => null,
                'title' => (string) ($candidate['title'] ?? ''),
                'details' => (string) ($candidate['details'] ?? ''),
                'assignee_name' => $candidate['assignee_name'] ?? null,
                'due_text' => $candidate['due_text'] ?? null,
                'priority' => (string) ($candidate['priority'] ?? 'medium'),
                'status' => 'open',
                'meta' => [
                    'origin' => 'najm_hoda_semantic_proposal',
                    'confirmed_by_user_id' => $requester->id,
                    'source' => $source,
                    'evidence' => (string) ($candidate['evidence'] ?? ''),
                ],
            ]);
            $created++;
        }
        return $created;
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

    protected function looksLikeExtractionRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        $actionWords = ['action item', 'اکشن آیتم', 'اکشن ایتم', 'موارد اقدام', 'مورد اقدام', 'کارهای قابل اقدام', 'وظایف پیشنهادی', 'اقدامات پیشنهادی'];
        $verbs = ['استخراج', 'پیشنهاد', 'پیدا کن', 'در بیار', 'درآر', 'بساز', 'تهیه کن', 'مشخص کن'];
        return $this->containsAny($plain, $actionWords) && $this->containsAny($plain, $verbs);
    }

    /** @return array{0:\Carbon\CarbonInterface,1:\Carbon\CarbonInterface,2:string} */
    protected function resolveWindow(string $message): array
    {
        $plain = $this->normalize($message);
        $now = now();
        if (str_contains($plain, 'دیروز')) return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'دیروز'];
        if (str_contains($plain, 'این هفته') || str_contains($plain, 'هفته جاری')) return [$now->copy()->startOfWeek(), $now->copy(), 'این هفته'];
        if (str_contains($plain, '24 ساعت') || str_contains($plain, '۲۴ ساعت')) return [$now->copy()->subDay(), $now->copy(), '۲۴ ساعت اخیر'];
        return [$now->copy()->startOfDay(), $now->copy(), 'امروز'];
    }

    protected function pendingKey(int $userId, ?int $conversationId, int $groupId): string
    {
        return 'najm_hoda:private_group_action_items:' . $groupId . ':' . $userId . ':' . ($conversationId ?: 0);
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

    protected function priorityLabel(string $priority): string
    {
        return match ($priority) { 'high' => 'زیاد', 'low' => 'کم', default => 'متوسط' };
    }

    protected function sourceLabel(string $source): string
    {
        if (preg_match('/^(message|post|poll):(\d+)$/', $source, $m) !== 1) return 'منبع گروه';
        $label = match ($m[1]) { 'message' => 'پیام', 'post' => 'پست', 'poll' => 'نظرسنجی', default => 'منبع' };
        return $label . ' #' . $m[2];
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
            'private_group_action_items' => true,
            'status' => $status,
        ];
    }
}
