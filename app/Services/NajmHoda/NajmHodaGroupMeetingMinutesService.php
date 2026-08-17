<?php

namespace App\Services\NajmHoda;

use App\Models\GroupSession;
use App\Models\Message;
use App\Models\Blog;
use App\Models\Poll;
use App\Models\NajmHodaGroupMeetingMinute;
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
        $minute->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $minute->fresh();
    }

    protected function userName($user): string
    {
        if (! $user) return 'کاربر ناشناس';
        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        return $name !== '' ? $name : ($user->email ?? ('user#' . $user->id));
    }
}
