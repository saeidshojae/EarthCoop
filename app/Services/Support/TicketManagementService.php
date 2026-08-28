<?php

namespace App\Services\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\EmailTicketIntegrationService;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class TicketManagementService
{
    public function __construct(protected EmailTicketIntegrationService $emailService) {}

    public function classify(Ticket $ticket): array
    {
        $text = mb_strtolower(trim((string) $ticket->subject . ' ' . (string) $ticket->message));
        $category = $this->detectCategory($text);

        if ((string) $ticket->category !== $category) {
            $ticket->category = $category;
            $ticket->save();
        }

        return ['ticket_id' => (int) $ticket->id, 'category' => $category];
    }

    public function assignPriority(Ticket $ticket): array
    {
        $text = mb_strtolower(trim((string) $ticket->subject . ' ' . (string) $ticket->message));
        $priority = $this->detectPriority($text);

        if ((string) $ticket->priority !== $priority) {
            $ticket->priority = $priority;
            $ticket->save();
        }

        return ['ticket_id' => (int) $ticket->id, 'priority' => $priority];
    }

    public function assign(Ticket $ticket, ?int $assigneeId): array
    {
        $ticket->assignee_id = $assigneeId;
        $ticket->save();

        return ['ticket_id' => (int) $ticket->id, 'assignee_id' => $assigneeId];
    }

    public function reply(Ticket $ticket, int $actorUserId, string $message, bool $sendEmail = true): array
    {
        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actorUserId,
            'message' => $message,
        ]);

        if (!$ticket->first_response_at) $ticket->first_response_at = now();
        if ($ticket->status === 'open') $ticket->status = 'in-progress';
        $ticket->save();

        if ($sendEmail) {
            try {
                $this->emailService->sendTicketReplyToEmail($ticket, $comment);
            } catch (\Throwable $e) {
                Log::error('خطا در ارسال ایمیل پاسخ تیکت', [
                    'ticket_id' => $ticket->id,
                    'exception_class' => $e::class,
                ]);
            }
        }

        return [
            'ticket_id' => (int) $ticket->id,
            'comment_id' => (int) $comment->id,
            'status' => (string) $ticket->status,
        ];
    }

    public function changeStatus(Ticket $ticket, string $status): array
    {
        if (!in_array($status, ['open', 'in-progress', 'closed'], true)) {
            throw new InvalidArgumentException('invalid_ticket_status');
        }

        $ticket->status = $status;
        if ($status === 'closed') {
            $ticket->resolved_at = now();
        } elseif ($ticket->resolved_at) {
            $ticket->resolved_at = null;
        }
        $ticket->save();

        return ['ticket_id' => (int) $ticket->id, 'status' => $status];
    }

    public function close(Ticket $ticket): array
    {
        return $this->changeStatus($ticket, 'closed');
    }

    protected function detectCategory(string $text): string
    {
        $rules = [
            'security' => ['امنیت','هک','نفوذ','رمز','password','security','hack','fraud','کلاهبرداری'],
            'billing' => ['پرداخت','تراکنش','کیف پول','بهار','سهام','مزایده','payment','transaction','wallet','stock','auction'],
            'registration' => ['ثبت نام','ثبت‌نام','احراز','ایمیل تایید','verification','register','signup','profile'],
            'governance' => ['انتخابات','رای','رأی','گروه','مدیر','بازرس','election','vote','governance'],
            'technical' => ['خطا','ارور','کار نمی','باز نمی','لود','error','bug','failed','exception','not working','login'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, mb_strtolower($keyword))) return $category;
            }
        }
        return 'general';
    }

    protected function detectPriority(string $text): string
    {
        $high = ['فوری','بحرانی','امنیت','هک','نفوذ','کلاهبرداری','پول کم','برداشت','urgent','critical','security','hack','fraud','locked out'];
        foreach ($high as $keyword) {
            if (str_contains($text, mb_strtolower($keyword))) return 'high';
        }

        $low = ['پیشنهاد','تشکر','راهنما','اطلاعات','suggestion','thanks','how to','information'];
        foreach ($low as $keyword) {
            if (str_contains($text, mb_strtolower($keyword))) return 'low';
        }

        return 'normal';
    }
}
