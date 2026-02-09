<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $expireAt;

    public function __construct($code, $expireAt = null)
    {
        $this->code = $code;
        $this->expireAt = $expireAt;
    }

    public function build()
    {
        return $this->subject('کد دعوت شما آماده است - Earth Coop')
            ->view('emails.invitation')
            ->with([
                'code' => $this->code,
                'expireAt' => $this->expireAt
            ]);
    }
}