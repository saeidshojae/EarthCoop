<?php

namespace App\Services\Support;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Str;

class TicketReplyDraftService
{
    public function __construct(
        protected StewardAgent $steward,
        protected RuntimeEventBus $events
    ) {}

    /** @return array<string,mixed> */
    public function generate(Ticket $ticket, ?string $reasonCode = null): array
    {
        if (! in_array((string) $ticket->status, ['open', 'in-progress'], true)) {
            return ['success' => false, 'status' => 'skipped', 'reason' => 'ticket_not_active'];
        }

        $existing = SupportReplyDraft::query()
            ->where('ticket_id', $ticket->id)
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        if ($existing) {
            return $this->summary($existing, 'existing');
        }

        $history = $ticket->comments()
            ->latest('id')
            ->limit(8)
            ->get(['user_id', 'message', 'created_at'])
            ->reverse()
            ->values()
            ->map(fn ($comment) => [
                'role' => $comment->user_id ? 'participant' : 'system',
                'message' => mb_substr((string) $comment->message, 0, 1200),
            ])->all();

        $prompt = $this->prompt($ticket, $history);
        $body = trim((string) $this->steward->ask($prompt, [
            'support_draft' => true,
            'ticket_id' => (int) $ticket->id,
        ]));

        if ($body === '') {
            return ['success' => false, 'status' => 'failed', 'reason' => 'empty_draft'];
        }

        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'source' => 'najm_hoda',
            'body' => $body,
            'status' => 'draft',
            'reason_code' => $reasonCode ? Str::limit($reasonCode, 100, '') : null,
        ]);

        $this->events->emit('najm_hoda.input.support.reply_draft.created', [
            'ticket_id' => (int) $ticket->id,
            'draft_id' => (int) $draft->id,
            'status' => 'draft',
            'reason_code' => $draft->reason_code,
        ]);

        return $this->summary($draft, 'created');
    }

    /** @param array<int,array<string,mixed>> $history */
    protected function prompt(Ticket $ticket, array $history): string
    {
        $historyText = collect($history)->map(
            fn (array $item) => '- ' . ($item['role'] ?? 'participant') . ': ' . ($item['message'] ?? '')
        )->implode("\n");

        return "برای تیکت پشتیبانی زیر فقط یک پاسخ پیشنهادی آماده کن. آن را ارسال نکن و ادعای انجام عملیاتی نکن که واقعاً انجام نشده است. اگر اطلاعات کافی نیست، دقیقاً بگو چه اطلاعاتی باید از کاربر خواسته شود. پاسخ باید کوتاه، روشن و به زبان کاربر باشد.\n\n"
            . "موضوع: " . mb_substr((string) $ticket->subject, 0, 500) . "\n"
            . "دسته: " . ((string) $ticket->category ?: 'نامشخص') . "\n"
            . "اولویت: " . ((string) $ticket->priority ?: 'normal') . "\n"
            . "شرح اولیه:\n" . mb_substr((string) $ticket->message, 0, 4000) . "\n\n"
            . "آخرین پیام‌های مرتبط:\n" . ($historyText ?: 'بدون پیام تکمیلی');
    }

    /** @return array<string,mixed> */
    protected function summary(SupportReplyDraft $draft, string $mode): array
    {
        return [
            'success' => true,
            'status' => 'draft_ready',
            'mode' => $mode,
            'draft_id' => (int) $draft->id,
            'ticket_id' => (int) $draft->ticket_id,
            'draft_status' => (string) $draft->status,
        ];
    }
}
