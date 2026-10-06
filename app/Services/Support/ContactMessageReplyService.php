<?php

namespace App\Services\Support;

use App\Models\Communication;
use App\Models\ContactMessage;
use App\Services\Communication\CommunicationDispatcher;
use DomainException;

class ContactMessageReplyService
{
    public function __construct(
        private readonly CommunicationDispatcher $communications,
    ) {
    }

    public function reply(ContactMessage $message, string $body, ?int $actorId = null): Communication
    {
        if ($message->status === 'spam' || $message->converted_ticket_id) {
            throw new DomainException('contact_message_reply_not_allowed');
        }

        $email = trim((string) $message->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('contact_message_valid_email_required');
        }

        $communication = $this->communications->dispatchExternal(
            'contact.reply',
            ['type' => 'contact_message', 'id' => (string) $message->id],
            [['email' => $email, 'locale' => 'fa']],
            [
                'subject' => $message->subject,
                'rendered_html' => nl2br(e($body)),
            ],
            ['priority' => 2],
        );

        $message->update([
            'status' => 'replied',
            'handled_by' => $actorId,
            'handled_at' => now(),
        ]);

        return $communication;
    }
}
