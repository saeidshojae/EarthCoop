<?php

namespace App\Services\NajmHoda;

use App\Models\Blog;
use App\Models\GroupSession;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\Poll;
use App\Models\User;

class NajmHodaGroupMeetingMinutesService
{
    /** @return array<string,mixed> */
    public function captureEvidence(GroupSession $session): array
    {
        $from = $session->started_at ?: $session->starts_at;
        $to = $session->ended_at ?: now();

        $messages = Message::query()
            ->where('group_id', $session->group_id)
            ->whereBetween('created_at', [$from, $to])
            ->with('user:id,first_name,last_name,email')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $m) => [
                'type' => 'message',
                'id' => (int) $m->id,
                'author' => $this->userName($m->user),
                'text' => trim(strip_tags((string) $m->message)),
                'created_at' => optional($m->created_at)->toIso8601String(),
            ])->values()->all();

        $posts = Blog::query()
            ->where('group_id', $session->group_id)
            ->whereBetween('created_at', [$from, $to])
            ->with('user:id,first_name,last_name,email')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Blog $p) => [
                'type' => 'post',
                'id' => (int) $p->id,
                'author' => $this->userName($p->user),
                'title' => (string) $p->title,
                'text' => trim(strip_tags((string) $p->content)),
                'created_at' => optional($p->created_at)->toIso8601String(),
            ])->values()->all();

        $polls = Poll::query()
            ->where('group_id', $session->group_id)
            ->whereBetween('created_at', [$from, $to])
            ->with(['user:id,first_name,last_name,email', 'options:id,poll_id,text'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Poll $p) => [
                'type' => 'poll',
                'id' => (int) $p->id,
                'author' => $this->userName($p->user),
                'question' => (string) $p->question,
                'options' => $p->options->pluck('text')->values()->all(),
                'created_at' => optional($p->created_at)->toIso8601String(),
            ])->values()->all();

        $participants = collect($messages)->pluck('author')
            ->merge(collect($posts)->pluck('author'))
            ->merge(collect($polls)->pluck('author'))
            ->filter()->unique()->values()->all();

        return [
            'session' => [
                'id' => (int) $session->id,
                'group_id' => (int) $session->group_id,
                'title' => (string) $session->title,
                'subject' => $session->subject,
                'agenda' => $session->agenda,
                'started_at' => optional($session->started_at)->toIso8601String(),
                'ended_at' => optional($session->ended_at)->toIso8601String(),
            ],
            'participants' => $participants,
            'counts' => [
                'messages' => count($messages),
                'posts' => count($posts),
                'polls' => count($polls),
            ],
            'messages' => $messages,
            'posts' => $posts,
            'polls' => $polls,
        ];
    }

    public function generateDraft(GroupSession $session, ?User $generator = null): NajmHodaGroupMeetingMinute
    {
        $evidence = $this->captureEvidence($session);
        $counts = $evidence['counts'];
        $participants = $evidence['participants'];

        $summaryLines = [
            'خلاصه داده‌محور نشست «' . $session->title . '»',
            '• پیام‌ها: ' . $counts['messages'],
            '• پست‌ها: ' . $counts['posts'],
            '• نظرسنجی‌ها: ' . $counts['polls'],
            '• مشارکت‌کنندگان قابل استناد: ' . (count($participants) ? implode('، ', $participants) : 'ثبت نشده'),
        ];

        $minuteLines = [
            'پیش‌نویس صورتجلسه رسمی',
            'عنوان: ' . $session->title,
            'موضوع: ' . ($session->subject ?: 'ثبت نشده'),
            'دستور جلسه: ' . ($session->agenda ?: 'ثبت نشده'),
            'آغاز: ' . optional($session->started_at)->toDateTimeString(),
            'پایان: ' . optional($session->ended_at)->toDateTimeString(),
            '',
            'فعالیت ثبت‌شده در بازه رسمی نشست:',
            '• ' . $counts['messages'] . ' پیام، ' . $counts['posts'] . ' پست و ' . $counts['polls'] . ' نظرسنجی.',
            '',
            'تصمیمات/مصوبات قطعی:',
            '• هنوز موردی به عنوان مصوبه قطعی تأیید نشده است.',
            '',
            'این متن پیش‌نویس داده‌محور است و تا قبل از تأیید مدیر/بازرس، صورتجلسه رسمی نهایی محسوب نمی‌شود.',
        ];

        return NajmHodaGroupMeetingMinute::updateOrCreate(
            ['group_session_id' => $session->id],
            [
                'group_id' => $session->group_id,
                'status' => 'draft',
                'summary' => implode("\n", $summaryLines),
                'minutes' => implode("\n", $minuteLines),
                'evidence_snapshot' => $evidence,
                'decision_candidates' => [],
                'action_candidates' => [],
                'generated_by' => $generator?->id,
                'generated_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
            ]
        );
    }

    public function approve(NajmHodaGroupMeetingMinute $minute, User $approver): NajmHodaGroupMeetingMinute
    {
        // Freeze the official document at approval time. Subsequent action-state
        // changes are displayed as a live execution layer and do not rewrite this snapshot.
        $minute->minutes = $this->renderManagementDocument($minute, false);
        $minute->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $minute->fresh();
    }

    public function renderManagementDocument(NajmHodaGroupMeetingMinute $minute, bool $includeLiveExecution = true): string
    {
        $minute->loadMissing('session');
        $session = $minute->session;
        $evidence = is_array($minute->evidence_snapshot) ? $minute->evidence_snapshot : [];
        $counts = is_array($evidence['counts'] ?? null) ? $evidence['counts'] : [];
        $participants = array_values((array) ($evidence['participants'] ?? []));
        $decisions = collect((array) $minute->decision_candidates)
            ->filter(fn ($item): bool => is_array($item) && (string) ($item['state'] ?? '') === 'confirmed')
            ->values();
        $actions = $this->meetingActions($minute);

        $lines = [
            $minute->status === 'approved' ? 'صورتجلسه رسمی نشست' : 'پیش‌نویس صورتجلسه رسمی',
            'عنوان: ' . ($session?->title ?: data_get($evidence, 'session.title', 'ثبت نشده')),
            'موضوع: ' . ($session?->subject ?: data_get($evidence, 'session.subject', 'ثبت نشده')),
            'دستور جلسه: ' . ($session?->agenda ?: data_get($evidence, 'session.agenda', 'ثبت نشده')),
            'آغاز: ' . ($session?->started_at?->toDateTimeString() ?: data_get($evidence, 'session.started_at', 'ثبت نشده')),
            'پایان: ' . ($session?->ended_at?->toDateTimeString() ?: data_get($evidence, 'session.ended_at', 'ثبت نشده')),
            '',
            'حضور و مشارکت قابل استناد:',
            '• مشارکت‌کنندگان: ' . ($participants !== [] ? implode('، ', $participants) : 'ثبت نشده'),
            '• فعالیت ثبت‌شده: ' . (int) ($counts['messages'] ?? 0) . ' پیام، ' . (int) ($counts['posts'] ?? 0) . ' پست و ' . (int) ($counts['polls'] ?? 0) . ' نظرسنجی.',
            '',
            'تصمیمات/مصوبات تأییدشده:',
        ];

        if ($decisions->isEmpty()) {
            $lines[] = '• هنوز تصمیم یا مصوبه‌ای با تأیید انسانی ثبت نشده است.';
        } else {
            foreach ($decisions as $i => $decision) {
                $lines[] = '• ' . ($i + 1) . '. ' . (string) ($decision['decision'] ?? $decision['title'] ?? 'تصمیم تأییدشده');
                $lines[] = '  شاهد: «' . (string) ($decision['evidence'] ?? '') . '» (' . (string) ($decision['source'] ?? '-') . ')';
            }
        }

        $lines[] = '';
        $lines[] = 'اقدامات اجرایی ناشی از نشست:';
        if ($actions->isEmpty()) {
            $lines[] = '• هنوز اقدام تأییدشده‌ای از این نشست در صف اجرا ثبت نشده است.';
        } else {
            foreach ($actions as $action) {
                $parts = ['وضعیت: ' . $this->statusLabel((string) $action->status)];
                if (trim((string) $action->assignee_name) !== '') $parts[] = 'مسئول: ' . $action->assignee_name;
                elseif ($action->assignedUser) $parts[] = 'مسئول: ' . $this->userName($action->assignedUser);
                else $parts[] = 'مسئول: تعیین نشده';
                if ($action->due_at) $parts[] = 'موعد: ' . $action->due_at->format('Y-m-d H:i');
                elseif (trim((string) $action->due_text) !== '') $parts[] = 'موعد: ' . $action->due_text;
                $meta = is_array($action->meta) ? $action->meta : [];
                if (trim((string) ($meta['decision_title'] ?? '')) !== '') $parts[] = 'مرتبط با تصمیم: ' . $meta['decision_title'];
                $lines[] = '• ' . $action->title . ' — ' . implode(' | ', $parts);
            }
        }

        if ($includeLiveExecution && $minute->status === 'approved') {
            $lines[] = '';
            $lines[] = 'وضعیت اجرایی جاری:';
            $active = $actions->whereNotIn('status', ['done', 'cancelled']);
            $done = $actions->where('status', 'done');
            $blocked = $actions->where('status', 'blocked');
            $overdue = $actions->filter(fn (NajmHodaGroupActionItem $item): bool => $item->due_at && $item->due_at->isPast() && ! in_array((string) $item->status, ['done', 'cancelled'], true));
            $lines[] = '• فعال: ' . $active->count() . ' | انجام‌شده: ' . $done->count() . ' | مسدود: ' . $blocked->count() . ' | معوق: ' . $overdue->count();
            $lines[] = '• این بخش وضعیت جاری صف اقدام است و جزء snapshot تاریخیِ زمان تصویب صورتجلسه محسوب نمی‌شود.';
        }

        $lines[] = '';
        $lines[] = $minute->status === 'approved'
            ? 'این صورتجلسه با تأیید مدیر/بازرس رسمی شده است؛ شواهد و تصمیمات تأییدشده مرجع تاریخی سند هستند.'
            : 'این سند تا قبل از تأیید مدیر/بازرس، صورتجلسه رسمی نهایی محسوب نمی‌شود.';

        return implode("\n", $lines);
    }

    /** @return \Illuminate\Support\Collection<int,NajmHodaGroupActionItem> */
    protected function meetingActions(NajmHodaGroupMeetingMinute $minute)
    {
        return NajmHodaGroupActionItem::query()
            ->where('group_id', $minute->group_id)
            ->where('meta->meeting_minute_id', (int) $minute->id)
            ->with('assignedUser:id,first_name,last_name,email')
            ->orderBy('id')
            ->get();
    }

    protected function statusLabel(string $status): string
    {
        return [
            'open' => 'باز',
            'in_progress' => 'در حال انجام',
            'blocked' => 'مسدود',
            'done' => 'انجام‌شده',
            'cancelled' => 'لغوشده',
        ][$status] ?? $status;
    }

    protected function userName($user): string
    {
        if (! $user) return 'کاربر ناشناس';
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : ($user->email ?? ('user#' . $user->id));
    }
}
