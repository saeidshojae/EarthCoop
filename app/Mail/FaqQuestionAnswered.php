<?php

namespace App\Mail;

use App\Models\FaqQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FaqQuestionAnswered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FaqQuestion $question)
    {
    }

    public function build(): self
    {
        return $this->subject('پاسخ به سوال شما در EarthCoop')
            ->view('emails.faq.answered')
            ->with([
                'question' => $this->question,
            ]);
    }
}

