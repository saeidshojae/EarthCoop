<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DynamicMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $body;
    public $variables;

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $body, array $variables = [])
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->variables = $variables;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject($this->subject)
            ->html($this->body);
    }

    /**
     * Create from EmailTemplate
     */
    public static function fromTemplate(EmailTemplate $template, array $variables = []): self
    {
        $rendered = $template->render($variables);
        return new self($rendered['subject'], $rendered['body'], $variables);
    }
}

