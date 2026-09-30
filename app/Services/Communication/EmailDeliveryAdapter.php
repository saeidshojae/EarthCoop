<?php

namespace App\Services\Communication;

use App\Models\CommunicationRecipient;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailDeliveryAdapter
{
    public function __construct(
        private readonly CommunicationTemplateRenderer $renderer,
    ) {
    }

    /** @return array{provider:string,provider_message_id:?string} */
    public function send(CommunicationRecipient $recipient): array
    {
        $recipient->loadMissing([
            'communication',
            'templateVersion.senderIdentity',
        ]);

        $communication = $recipient->communication;
        $version = $recipient->templateVersion;
        $sender = $version?->senderIdentity;

        if (! $communication || ! $version) {
            throw new RuntimeException('communication_delivery_context_missing');
        }

        if (! $sender || ! $sender->is_active) {
            throw new RuntimeException('communication_sender_not_available');
        }

        $rendered = $this->renderer->render(
            $version,
            $communication->context_snapshot ?? [],
        );

        Mail::html($rendered['body'], function ($message) use ($recipient, $rendered, $sender): void {
            $message->to($recipient->email)
                ->subject($rendered['subject'])
                ->from($sender->email, $sender->display_name);

            if ($sender->reply_to) {
                $message->replyTo($sender->reply_to);
            }
        });

        return [
            'provider' => 'laravel-mail',
            'provider_message_id' => null,
        ];
    }
}
