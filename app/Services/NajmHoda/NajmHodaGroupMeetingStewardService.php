<?php

namespace App\Services\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\GroupChat\GroupSessionService;
use Carbon\Carbon;

/**
 * Grounded meeting-stewardship layer for Najm Hoda.
 *
 * It deliberately reuses the canonical GroupSession model/service used by the
 * group chat. It never creates a parallel meeting system. Commands are split
 * into preview and execute so the private Najm Hoda widget can require an
 * explicit confirmation before mutating group state.
 */
class NajmHodaGroupMeetingStewardService
{
    public function __construct(protected GroupSessionService $sessions)
    {
    }

    /** @return array<string,mixed> */
    public function preview(User $requester, Group $group, array $input): array
    {
        if (! $this->canManage($requester, $group)) {
            return [
                'allowed' => false,
                'message' => 'فقط مدیر یا بازرس فعال گروه می‌تواند نشست رسمی را از طریق نجم هدا مدیریت کند.',
            ];
        }

        $title = trim((string) ($input['title'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $agenda = trim((string) ($input['agenda'] ?? ''));
        $startsAt = $this->parseStartsAt($input['starts_at'] ?? null);

        if ($title === '') {
            $title = 'نشست گروه ' . trim((string) $group->name);
        }

        if (! $startsAt) {
            return [
                'allowed' => true,
                'needs_input' => true,
                'message' => 'زمان نشست را مشخص کنید؛ برای شروع فوری «الان» یا برای برنامه‌ریزی تاریخ و ساعت را به‌صورت YYYY-MM-DD HH:MM بفرستید.',
            ];
        }

        $payload = [
            'title' => mb_substr($title, 0, 160),
            'subject' => $subject !== '' ? mb_substr($subject, 0, 1000) : null,
            'agenda' => $agenda !== '' ? mb_substr($agenda, 0, 3000) : null,
            'starts_at' => $startsAt->toIso8601String(),
        ];

        $when = $startsAt->lte(now()) ? 'شروع فوری' : $startsAt->format('Y-m-d H:i');
        $lines = [
            'نوع اقدام: تنظیم نشست رسمی گروه',
            'عنوان: ' . $payload['title'],
            'زمان: ' . $when,
        ];
        if ($payload['subject']) {
            $lines[] = 'موضوع: ' . $payload['subject'];
        }
        if ($payload['agenda']) {
            $lines[] = 'دستور جلسه: ' . $payload['agenda'];
        }
        $lines[] = 'ثبت در سامانه رسمی نشست‌های همین گروه: بله';

        return [
            'allowed' => true,
            'needs_input' => false,
            'payload' => $payload,
            'preview' => implode("\n", $lines),
        ];
    }

    /** @param array<string,mixed> $payload
     *  @return array<string,mixed>
     */
    public function executeConfirmed(User $requester, Group $group, array $payload): array
    {
        if (! $this->canManage($requester, $group)) {
            return [
                'decision' => 'skipped',
                'reason' => 'meeting_management_denied',
                'group_reply' => 'مجوز مدیریت نشست رسمی این گروه را ندارید.',
            ];
        }

        $startsAt = $this->parseStartsAt($payload['starts_at'] ?? null);
        $title = trim((string) ($payload['title'] ?? ''));
        if (! $startsAt || $title === '') {
            return [
                'decision' => 'failed',
                'reason' => 'confirmed_meeting_payload_invalid',
                'group_reply' => 'اطلاعات تأییدشده نشست ناقص است.',
            ];
        }

        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $requester->id,
            'title' => mb_substr($title, 0, 160),
            'subject' => $this->nullableText($payload['subject'] ?? null, 1000),
            'agenda' => $this->nullableText($payload['agenda'] ?? null, 3000),
            'starts_at' => $startsAt,
            'status' => 'scheduled',
        ]);

        if ($startsAt->isFuture()) {
            $this->sessions->scheduled($session, (int) $requester->id);
            $reply = 'نشست رسمی برای زمان تعیین‌شده برنامه‌ریزی شد.';
        } else {
            $session = $this->sessions->start($session, (int) $requester->id);
            $reply = 'نشست رسمی آغاز شد و وضعیت گروه مطابق قواعد نشست فعال شد.';
        }

        return [
            'decision' => 'executed',
            'reason' => $session->status === 'active' ? 'meeting_started' : 'meeting_scheduled',
            'group_reply' => $reply,
            'context' => [
                'action' => 'manage_meeting',
                'session_id' => (int) $session->id,
                'status' => (string) $session->status,
                'starts_at' => $session->starts_at?->toIso8601String(),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function currentState(Group $group): array
    {
        $active = GroupSession::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        $scheduled = GroupSession::query()
            ->where('group_id', $group->id)
            ->where('status', 'scheduled')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (GroupSession $session) => $this->sessions->payload($session))
            ->values()
            ->all();

        return [
            'active' => $active ? $this->sessions->payload($active) : null,
            'scheduled' => $scheduled,
        ];
    }

    protected function canManage(User $requester, Group $group): bool
    {
        return GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $requester->id)
            ->where('status', 1)
            ->whereIn('role', [2, 3])
            ->exists();
    }

    protected function parseStartsAt(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (in_array(mb_strtolower($raw), ['الان', 'اکنون', 'now'], true)) {
            return now();
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nullableText(mixed $value, int $limit): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : mb_substr($text, 0, $limit);
    }
}
