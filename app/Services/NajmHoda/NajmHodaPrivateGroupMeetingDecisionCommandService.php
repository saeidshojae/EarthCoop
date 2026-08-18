<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Services\GroupChat\GroupSessionService;
use Illuminate\Support\Facades\Cache;

/**
 * Adds evidence-grounded decision confirmation to the existing private meeting
 * command workflow without duplicating the established session/action logic.
 */
class NajmHodaPrivateGroupMeetingDecisionCommandService extends NajmHodaPrivateGroupMeetingCommandService
{
    public function __construct(
        NajmHodaGroupMeetingStewardService $meetings,
        GroupSessionService $sessions,
        NajmHodaGroupMeetingMinutesService $minutes,
        NajmHodaGroupActionCandidateService $actionCandidates,
        protected NajmHodaGroupDecisionCandidateService $decisionCandidates
    ) {
        parent::__construct($meetings, $sessions, $minutes, $actionCandidates);
    }

    /** @param array<string,mixed> $pageContext @return array<string,mixed>|null */
    public function intercept(User $requester, array $pageContext, string $message, ?int $conversationId = null): ?array
    {
        if ((string) ($pageContext['page_kind'] ?? '') !== 'group_chat') {
            return parent::intercept($requester, $pageContext, $message, $conversationId);
        }

        $resource = is_array($pageContext['resource'] ?? null) ? $pageContext['resource'] : [];
        $groupId = (int) ($pageContext['resource_id'] ?? $resource['id'] ?? 0);
        $group = $groupId > 0 ? Group::query()->find($groupId) : null;
        if (! $group) return parent::intercept($requester, $pageContext, $message, $conversationId);

        $key = $this->pendingKey((int) $requester->id, $conversationId, $groupId);
        $pending = Cache::get($key);

        if (is_array($pending) && (string) ($pending['operation'] ?? '') === 'confirm_meeting_decisions') {
            if ($this->isCancellation($message)) {
                Cache::forget($key);
                return $this->response('تأیید تصمیمات پیشنهادی لغو شد و هیچ مصوبه‌ای رسمی نشد.', 'cancelled');
            }
            if ($this->isConfirmation($message)) {
                Cache::forget($key);
                if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();
                return $this->confirmDecisions($requester, $group, $pending);
            }
        }

        $actionPending = is_array($pending) && (string) ($pending['operation'] ?? '') === 'persist_meeting_actions'
            && $this->isConfirmation($message) ? $pending : null;

        if ($this->looksLikeDecisionExtraction($message)) {
            if (! $this->isLeadership($groupId, (int) $requester->id)) return $this->blocked();

            $session = $this->latestEndedSession($groupId);
            if (! $session) return $this->response('نشست پایان‌یافته‌ای برای استخراج تصمیمات پیدا نشد.', 'no_ended_meeting');

            $from = $session->started_at ?: $session->starts_at;
            $to = $session->ended_at ?: now();
            $result = $this->decisionCandidates->extract($group, $from, $to);
            $minute = NajmHodaGroupMeetingMinute::query()->where('group_session_id', $session->id)->first()
                ?: $this->minutes->generateDraft($session, $requester);

            if (! (bool) ($result['available'] ?? false)) {
                return $this->response('تحلیل معنایی تصمیمات نشست در حال حاضر در دسترس نیست؛ برای جلوگیری از حدس، هیچ مصوبه‌ای ثبت نشد.', 'provider_unavailable');
            }

            $candidates = array_values((array) ($result['candidates'] ?? []));
            $minute->update(['decision_candidates' => $candidates]);
            if ($candidates === []) {
                return $this->response('در evidence واقعی این نشست تصمیم یا مصوبه قابل اتکایی پیدا نشد.', 'no_candidates');
            }

            Cache::put($key, [
                'operation' => 'confirm_meeting_decisions',
                'minute_id' => (int) $minute->id,
                'session_id' => (int) $session->id,
                'candidates' => $candidates,
            ], now()->addMinutes(20));

            $lines = ['تصمیمات/مصوبات پیشنهادی از evidence همین نشست:', ''];
            foreach ($candidates as $i => $candidate) {
                $lines[] = ($i + 1) . ') ' . (string) ($candidate['title'] ?? 'تصمیم پیشنهادی');
                $lines[] = 'متن تصمیم: ' . (string) ($candidate['decision'] ?? '');
                $lines[] = 'شاهد: «' . (string) ($candidate['evidence'] ?? '') . '» (' . (string) ($candidate['source'] ?? '-') . ')';
                $lines[] = '';
            }
            $lines[] = 'این موارد هنوز رسمی نیستند. برای تأیید همین فهرست و درج در صورتجلسه «تأیید» بفرستید؛ برای انصراف «لغو».';
            return $this->response(implode("\n", $lines), 'awaiting_confirmation');
        }

        $response = parent::intercept($requester, $pageContext, $message, $conversationId);
        if ($actionPending !== null && is_array($response) && (string) ($response['action_status'] ?? '') === 'executed') {
            $this->linkActionsToConfirmedDecisions($groupId, $actionPending);
        }
        return $response;
    }

    /** @param array<string,mixed> $pending @return array<string,mixed> */
    protected function confirmDecisions(User $requester, Group $group, array $pending): array
    {
        $minute = NajmHodaGroupMeetingMinute::query()
            ->whereKey((int) ($pending['minute_id'] ?? 0))
            ->where('group_id', $group->id)
            ->first();
        if (! $minute) return $this->response('صورتجلسه مرجع پیدا نشد و هیچ تصمیمی تأیید نشد.', 'stale_minutes');

        $confirmedAt = now()->toIso8601String();
        $confirmed = [];
        foreach ((array) ($pending['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) continue;
            $candidate['state'] = 'confirmed';
            $candidate['confirmed_by_user_id'] = (int) $requester->id;
            $candidate['confirmed_at'] = $confirmedAt;
            $confirmed[] = $candidate;
        }

        $minute->decision_candidates = $confirmed;
        $minute->minutes = $this->renderConfirmedDecisionsIntoMinutes((string) $minute->minutes, $confirmed);
        $minute->save();

        return $this->response(
            'تصمیمات پیشنهادی با تأیید شما به‌عنوان تصمیمات تأییدشده این صورتجلسه ثبت شدند. خود صورتجلسه تا زمانی که جداگانه رسمی/تأیید نشود همچنان در وضعیت فعلی باقی می‌ماند.'
            . "\n\n" . $this->renderMinute($minute),
            'executed'
        );
    }

    /** @param array<int,array<string,mixed>> $confirmed */
    protected function renderConfirmedDecisionsIntoMinutes(string $minutes, array $confirmed): string
    {
        $lines = ['تصمیمات/مصوبات تأییدشده:'];
        foreach ($confirmed as $i => $candidate) {
            $lines[] = '• ' . ($i + 1) . '. ' . (string) ($candidate['decision'] ?? $candidate['title'] ?? 'تصمیم تأییدشده');
            $lines[] = '  شاهد: «' . (string) ($candidate['evidence'] ?? '') . '» (' . (string) ($candidate['source'] ?? '-') . ')';
        }
        $block = implode("\n", $lines);

        $pattern = '/تصمیمات\/مصوبات قطعی:\s*\n• هنوز موردی به عنوان مصوبه قطعی تأیید نشده است\./u';
        if (preg_match($pattern, $minutes) === 1) return preg_replace($pattern, $block, $minutes, 1) ?: $minutes;

        if (trim($minutes) === '') return $block;
        return rtrim($minutes) . "\n\n" . $block;
    }

    /** @param array<string,mixed> $pending */
    protected function linkActionsToConfirmedDecisions(int $groupId, array $pending): void
    {
        $minuteId = (int) ($pending['minute_id'] ?? 0);
        if ($minuteId <= 0) return;

        $minute = NajmHodaGroupMeetingMinute::query()->whereKey($minuteId)->where('group_id', $groupId)->first();
        if (! $minute) return;

        $confirmed = collect((array) $minute->decision_candidates)
            ->filter(fn ($item): bool => is_array($item) && (string) ($item['state'] ?? '') === 'confirmed')
            ->values();
        if ($confirmed->isEmpty()) return;

        NajmHodaGroupActionItem::query()
            ->where('group_id', $groupId)
            ->where('meta->meeting_minute_id', $minuteId)
            ->get()
            ->each(function (NajmHodaGroupActionItem $item) use ($confirmed): void {
                $meta = is_array($item->meta) ? $item->meta : [];
                if (! empty($meta['decision_fingerprint'])) return;

                $source = (string) ($meta['source'] ?? '');
                $decision = $confirmed->first(fn ($candidate): bool => (string) ($candidate['source'] ?? '') === $source);
                if (! is_array($decision)) return;

                $meta['decision_fingerprint'] = (string) ($decision['fingerprint'] ?? '');
                $meta['decision_title'] = (string) ($decision['title'] ?? '');
                $meta['decision_evidence'] = (string) ($decision['evidence'] ?? '');
                $item->meta = $meta;
                $item->save();
            });
    }

    protected function renderMinute(NajmHodaGroupMeetingMinute $minute): string
    {
        $status = $minute->status === 'approved' ? 'رسمی/تأییدشده' : 'پیش‌نویس';
        return "وضعیت: {$status}\n\n" . $this->minutes->renderManagementDocument($minute, true);
    }

    protected function looksLikeDecisionExtraction(string $message): bool
    {
        $plain = $this->normalize($message);
        return $this->containsAny($plain, ['جلسه', 'نشست', 'صورتجلسه', 'صورت جلسه'])
            && $this->containsAny($plain, ['تصمیم', 'تصمیمات', 'مصوبه', 'مصوبات', 'نتیجه قطعی', 'resolution'])
            && $this->containsAny($plain, ['استخراج', 'پیدا کن', 'در بیار', 'درآر', 'پیشنهاد', 'مشخص کن']);
    }
}
