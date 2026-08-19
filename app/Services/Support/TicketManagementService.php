<?php

namespace App\Services\Support;

use App\Models\Ticket;
use InvalidArgumentException;

class TicketManagementService
{
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

    public function assign(Ticket $ticket, ?int $assigneeId): void
    {
        $ticket->assignee_id = $assigneeId;
        $ticket->save();
    }

    public function close(Ticket $ticket): void
    {
        $ticket->status = 'closed';
        if ($ticket->isFillable('resolved_at') || array_key_exists('resolved_at', $ticket->getAttributes())) {
            $ticket->resolved_at = now();
        }
        $ticket->save();
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
