<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Services\GroupChat\GroupSessionService;
use Illuminate\Support\Facades\Cache;

class NajmHodaPrivateGroupMeetingCommandService
{
    public function __construct(
        protected NajmHodaGroupMeetingStewardService $meetings,
        protected GroupSessionService $sessions,
        protected NajmHodaGroupMeetingMinutesService $minutes,
        protected NajmHodaGroupActionCandidateService $actionCandidates
    ) {
    }

    /** @param array<string,mixed> $pageContext @return array<string,mixed>|null */
    public function intercept(User $requester, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if ((string) ($pageContext['page_kind'] ?? '') !== 'group_chat') return null;

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $groupId = (int) ($pageContext['resource_id'] ?? $resource['id'] ?? 0);
        if ($groupId <= 0) return null;

        $group = Group::query()->find($groupId);
        if (! $group) return null;

        $key = $this->pendingKey((int) $requester->id, $conversationId, $groupId);
        $pending = Cache::get($key);

        if (is_array($pending) && $this->isCancellation($message)) {
            Cache::forget($key);
            return $this->response('درخواست مدیریتی نشست لغو شد و هیچ تغییری اعمال نشد.', 'cancelled');
        }

        if (is_array($pending) && $this->isConfirmation($message)) {
            Cache::forget($key);
            return $this->executePending($requester, $group, $pending);
        }

        if ($this->looksLikeMinutesViewRequest($message)) {
            if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();
            $minute = $this->latestMinute($groupId);
            if (! $minute) return $this->response('برای این گروه هنوز پیش‌نویس صورتجلسه‌ای ثبت نشده است.', 'no_minutes');
            return $this->response($this->renderMinute($minute), 'minutes');
        }

        if ($this->looksLikeMinutesApprovalRequest($message)) {
            if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();
            $minute = NajmHodaGroupMeetingMinute::query()->where('group_id', $groupId)->where('status', 'draft')->latest('id')->first();
            if (! $minute) return $this->response('پیش‌نویس تأییدنشده‌ای برای این گروه وجود ندارد.', 'no_draft_minutes');

            Cache::put($key, ['operation' => 'approve_minutes', 'minute_id' => (int) $minute->id], now()->addMinutes(20));
            return $this->response(
                "صورتجلسه زیر آماده تأیید رسمی است:\n\n" . $this->renderMinute($minute)
                . "\n\nبا تأیید، وضعیت آن رسمی/approved می‌شود. برای ادامه «تأیید» و برای انصراف «لغو» بفرستید.",
                'awaiting_confirmation'
            );
        }

        if ($this->looksLikeMeetingActionExtraction($message)) {
            if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();
            $session = $this->latestEndedSession($groupId);
            if (! $session) return $this->response('نشست پایان‌یافته‌ای برای استخراج موارد اقدام پیدا نشد.', 'no_ended_meeting');

            $from = $session->started_at ?: $session->starts_at;
            $to = $session->ended_at ?: now();
            $result = $this->actionCandidates->extract($group, $from, $to);
            $minute = NajmHodaGroupMeetingMinute::query()->where('group_session_id', $session->id)->first()
                ?: $this->minutes->generateDraft($session, $requester);

            if (! (bool) ($result['available'] ?? false)) {
                return $this->response('تحلیل معنایی برای استخراج موارد اقدام این نشست در حال حاضر در دسترس نیست؛ برای جلوگیری از حدس، چیزی ثبت نشد.', 'provider_unavailable');
            }

            $candidates = array_values((array) ($result['candidates'] ?? []));
            $minute->update(['action_candidates' => $candidates]);
            if ($candidates === []) {
                return $this->response('در evidence واقعی این نشست مورد اقدام قابل اتکایی پیدا نشد.', 'no_candidates');
            }

            Cache::put($key, [
                'operation' => 'persist_meeting_actions',
                'minute_id' => (int) $minute->id,
                'session_id' => (int) $session->id,
                'candidates' => $candidates,
            ], now()->addMinutes(20));

            $lines = ['موارد اقدام پیشنهادی از evidence همین نشست:', ''];
            foreach ($candidates as $i => $candidate) {
                $lines[] = ($i + 1) . ') ' . (string) ($candidate['title'] ?? 'مورد اقدام');
                $lines[] = 'شاهد: «' . (string) ($candidate['evidence'] ?? '') . '» (' . (string) ($candidate['source'] ?? '-') . ')';
                if (! empty($candidate['assignee_name'])) $lines[] = 'مسئول پیشنهادی: ' . $candidate['assignee_name'];
                if (! empty($candidate['due_text'])) $lines[] = 'موعد پیشنهادی: ' . $candidate['due_text'];
                $lines[] = '';
            }
            $lines[] = 'این موارد هنوز مصوبه یا وظیفه قطعی نیستند. برای ثبت همین فهرست در صف اقدام «تأیید» بفرستید.';
            return $this->response(implode("\n", $lines), 'awaiting_confirmation');
        }

        if ($this->looksLikeMeetingEndRequest($message)) {
            if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();
            $active = GroupSession::query()->where('group_id', $groupId)->where('status', 'active')->latest('id')->first();
            if (! $active) return $this->response('در حال حاضر نشست رسمی فعالی برای پایان دادن وجود ندارد.', 'no_active_meeting');

            Cache::put($key, [
                'operation' => 'end_meeting',
                'session_id' => (int) $active->id,
            ], now()->addMinutes(20));
            return $this->response(
                "نشست فعال «{$active->title}» آماده پایان است. با پایان نشست، مشارکت عمومی باز می‌شود و نجم هدا به‌صورت خودکار evidence snapshot و پیش‌نویس صورتجلسه را ثبت می‌کند.\n\nبرای پایان «تأیید» و برای انصراف «لغو» بفرستید.",
                'awaiting_confirmation'
            );
        }

        if ($this->looksLikeStatusRequest($message)) {
            if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();
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

        if (! $this->looksLikeMeetingRequest($message)) return null;

        $input = $this->parseRequest($message);
        $preview = $this->meetings->preview($requester, $group, $input);
        if (! (bool) ($preview['allowed'] ?? false)) return $this->response((string) ($preview['message'] ?? 'مجوز مدیریت نشست را ندارید.'), 'blocked');
        if ((bool) ($preview['needs_input'] ?? false)) return $this->response((string) ($preview['message'] ?? 'اطلاعات نشست کامل نیست.'), 'needs_input');

        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];
        Cache::put($key, [
            'operation' => 'create_meeting',
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

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    protected function executePending(User $requester, Group $group, array $pending): array
    {
        $operation = (string) ($pending['operation'] ?? 'create_meeting');
        if (! $this->isLeadership((int) $group->id, (int) $requester->id)) return $this->blocked();

        if ($operation === 'create_meeting') {
            $result = $this->meetings->executeConfirmed($requester, $group, is_array($pending['payload'] ?? null) ? $pending['payload'] : []);
            return $this->executionResponse($result);
        }

        if ($operation === 'end_meeting') {
            $sessionId = (int) ($pending['session_id'] ?? 0);
            $active = GroupSession::query()->whereKey($sessionId)->where('group_id', $group->id)->where('status', 'active')->first();
            if (! $active) return $this->response('نشست موردنظر دیگر فعال نیست و تغییری انجام نشد.', 'stale_meeting');
            $ended = $this->sessions->end($group, (int) $requester->id);
            if (! $ended) return $this->response('نشست فعالی برای پایان یافت نشد.', 'no_active_meeting');
            $minute = NajmHodaGroupMeetingMinute::query()->where('group_session_id', $ended->id)->first();
            $message = 'نشست رسمی پایان یافت. مشارکت عمومی گروه دوباره فعال شد.';
            if ($minute) $message .= "\n\nنجم هدا پیش‌نویس صورتجلسه را نیز ثبت کرد:\n\n" . $this->renderMinute($minute);
            return $this->response($message, 'executed');
        }

        if ($operation === 'approve_minutes') {
            $minute = NajmHodaGroupMeetingMinute::query()->whereKey((int) ($pending['minute_id'] ?? 0))->where('group_id', $group->id)->where('status', 'draft')->first();
            if (! $minute) return $this->response('این پیش‌نویس دیگر در وضعیت قابل تأیید نیست.', 'stale_minutes');
            $approved = $this->minutes->approve($minute, $requester);
            return $this->response("صورتجلسه رسمی تأیید و ثبت شد.\n\n" . $this->renderMinute($approved), 'executed');
        }

        if ($operation === 'persist_meeting_actions') {
            $minute = NajmHodaGroupMeetingMinute::query()->whereKey((int) ($pending['minute_id'] ?? 0))->where('group_id', $group->id)->first();
            if (! $minute) return $this->response('صورتجلسه مرجع پیدا نشد و هیچ موردی ثبت نشد.', 'stale_minutes');
            $created = $this->persistMeetingActions($group, $requester, $minute, (array) ($pending['candidates'] ?? []));
            return $this->response("انجام شد. {$created} مورد تأییدشده از نشست در صف اقدام گروه ثبت شد و از این پس وارد چرخه پیگیری نجم هدا می‌شود.", 'executed');
        }

        return $this->response('عملیات نشست ناشناخته بود و چیزی تغییر نکرد.', 'failed');
    }

    /** @param array<int,array<string,mixed>> $candidates */
    protected function persistMeetingActions(Group $group, User $requester, NajmHodaGroupMeetingMinute $minute, array $candidates): int
    {
        $created = 0;
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) continue;
            $source = (string) ($candidate['source'] ?? '');
            $sourceMessageId = null;
            if (preg_match('/^message:(\d+)$/', $source, $m) === 1) {
                $id = (int) $m[1];
                $sourceMessageId = Message::query()->whereKey($id)->where('group_id', $group->id)->exists() ? $id : null;
            }

            $fingerprint = hash('sha256', $minute->group_session_id . '|' . $source . '|' . (string) ($candidate['title'] ?? ''));
            $exists = NajmHodaGroupActionItem::query()->where('group_id', $group->id)
                ->where('meta->meeting_action_fingerprint', $fingerprint)->exists();
            if ($exists) continue;

            NajmHodaGroupActionItem::create([
                'group_id' => $group->id,
                'source_message_id' => $sourceMessageId,
                'assigned_user_id' => null,
                'title' => (string) ($candidate['title'] ?? 'مورد اقدام نشست'),
                'details' => (string) ($candidate['details'] ?? ''),
                'assignee_name' => $candidate['assignee_name'] ?? null,
                'due_text' => $candidate['due_text'] ?? null,
                'priority' => (string) ($candidate['priority'] ?? 'medium'),
                'status' => 'open',
                'meta' => [
                    'origin' => 'najm_hoda_meeting_minutes',
                    'meeting_minute_id' => (int) $minute->id,
                    'group_session_id' => (int) $minute->group_session_id,
                    'confirmed_by_user_id' => (int) $requester->id,
                    'source' => $source,
                    'evidence' => (string) ($candidate['evidence'] ?? ''),
                    'meeting_action_fingerprint' => $fingerprint,
                ],
            ]);
            $created++;
        }
        return $created;
    }

    protected function latestMinute(int $groupId): ?NajmHodaGroupMeetingMinute
    {
        return NajmHodaGroupMeetingMinute::query()->where('group_id', $groupId)->latest('id')->first();
    }

    protected function latestEndedSession(int $groupId): ?GroupSession
    {
        return GroupSession::query()->where('group_id', $groupId)->where('status', 'ended')->latest('ended_at')->latest('id')->first();
    }

    protected function renderMinute(NajmHodaGroupMeetingMinute $minute): string
    {
        $status = $minute->status === 'approved' ? 'رسمی/تأییدشده' : 'پیش‌نویس';
        return "وضعیت: {$status}\n\n" . trim((string) $minute->summary) . "\n\n" . trim((string) $minute->minutes);
    }

    /** @return array<string,mixed> */
    protected function parseRequest(string $message): array
    {
        $plain = trim(strip_tags($message));
        $startsAt = null;
        if (preg_match('/(?:زمان|تاریخ|ساعت)\s*[:：]\s*([^|\n]+)/u', $plain, $m)) $startsAt = trim($m[1]);
        elseif (preg_match('/\b(\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{2})\b/u', $plain, $m)) $startsAt = trim($m[1]);
        elseif (preg_match('/(?:الان|اکنون|همین الان|همین‌الان)/u', $plain) === 1) $startsAt = 'الان';

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
        $pattern .= $stopPattern !== '' ? '(?=\s*(?:\||\n)\s*(?:' . $stopPattern . ')\s*[:：]|$)' : '$';
        $pattern .= '/us';
        return preg_match($pattern, $text, $m) === 1 ? trim((string) ($m[1] ?? '')) : '';
    }

    protected function looksLikeMeetingRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['نشست', 'جلسه رسمی', 'جلسه مدیریتی'])
            && $this->containsAny($plain, ['تنظیم کن', 'برگزار کن', 'برنامه ریزی کن', 'برنامه‌ریزی کن', 'بذار', 'بگذار', 'بساز', 'شروع کن']);
    }

    protected function looksLikeMeetingEndRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['جلسه', 'نشست']) && $this->containsAny($plain, ['پایان بده', 'تمام کن', 'خاتمه بده', 'ببند']);
    }

    protected function looksLikeMinutesViewRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['صورتجلسه', 'صورت جلسه']) && $this->containsAny($plain, ['نشان بده', 'ببینم', 'پیش نویس', 'پیش‌نویس', 'چی شد', 'وضعیت']);
    }

    protected function looksLikeMinutesApprovalRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['صورتجلسه', 'صورت جلسه']) && $this->containsAny($plain, ['تایید کن', 'تأیید کن', 'تصویب کن', 'رسمی کن']);
    }

    protected function looksLikeMeetingActionExtraction(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['جلسه', 'نشست', 'صورتجلسه', 'صورت جلسه'])
            && $this->containsAny($plain, ['مورد اقدام', 'موارد اقدام', 'اقدامات پیشنهادی', 'action item', 'اکشن آیتم'])
            && $this->containsAny($plain, ['استخراج', 'پیدا کن', 'در بیار', 'درآر', 'پیشنهاد']);
    }

    protected function looksLikeStatusRequest(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['نشست', 'جلسه رسمی', 'جلسه مدیریتی'])
            && $this->containsAny($plain, ['وضعیت', 'چه جلسه', 'چه نشست', 'برنامه ریزی شده', 'برنامه‌ریزی شده', 'فعال']);
    }

    protected function isLeadership(int $groupId, int $userId): bool
    {
        return GroupUser::query()->where('group_id', $groupId)->where('user_id', $userId)->where('status', 1)->whereIn('role', [2, 3])->exists();
    }

    protected function blocked(): array
    {
        return $this->response('مدیریت نشست و صورتجلسه از طریق نجم هدا فقط برای مدیران/بازرسان فعال گروه مجاز است.', 'blocked');
    }

    protected function containsAny(string $plain, array $needles): bool
    {
        foreach ($needles as $needle) if ($needle !== '' && mb_stripos($plain, $needle) !== false) return true;
        return false;
    }

    protected function normalize(string $message): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', strip_tags($message)) ?? ''));
    }

    protected function isConfirmation(string $message): bool
    {
        return in_array($this->normalize($message), ['تایید','تأیید','تایید کن','تأیید کن','بله','بله تایید','بله تأیید','انجام بده','اجرا کن','اوکی','ok','yes','confirm'], true);
    }

    protected function isCancellation(string $message): bool
    {
        return in_array($this->normalize($message), ['لغو','لغو کن','بیخیال','بی‌خیال','انصراف','cancel','no'], true);
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
        if ($decision === 'executed') return $this->response($detail !== '' ? $detail : 'نشست رسمی ثبت شد.', 'executed');
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
