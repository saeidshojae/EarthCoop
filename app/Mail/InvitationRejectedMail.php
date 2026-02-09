<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adminNote;

    public function __construct($adminNote = null)
    {
        $this->adminNote = $adminNote;
    }

    public function build()
    {
        return $this->subject('درخواست کد دعوت شما بررسی شد')
            ->view('emails.invitation-rejected')
            ->with(['adminNote' => $this->adminNote]);
    }
}

