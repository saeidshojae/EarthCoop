<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;

    public function __construct($ticket)
    {
        $this->ticket = $ticket;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $tracking = $this->ticket->tracking_code;
        $subject = "درخواست پشتیبانی دریافت شد - {$tracking}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('سلام')
            ->line("درخواست شما ثبت شد. شماره پیگیری شما: {$tracking}")
            ->line("موضوع: {$this->ticket->subject}")
            ->line('تیم پشتیبانی ما در اسرع وقت پاسخ خواهد داد.')
            ->salutation('با احترام، تیم ارث‌کوپ');
    }
}
