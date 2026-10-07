<?php

namespace App\Services\Communication;

use App\Models\CommunicationRecipient;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailDeliveryAdapter
{
    private const ALLOWED_DELIVERY_HEADERS = [
        'Message-ID',
        'In-Reply-To',
        'References',
    ];

    public function __construct(
        private readonly CommunicationTemplateRenderer $renderer,
        private readonly CommunicationEmailPresenter $presenter,
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

        $context = (array) ($communication->context_snapshot ?? []);
        $rendered = $this->renderer->render($version, $context);
        $html = $this->presenter->present($rendered['subject'], $rendered['body']);
        $headers = $this->safeDeliveryHeaders((array) ($context['_delivery_headers'] ?? []));

        Mail::html($html, function ($message) use ($recipient, $rendered, $sender, $headers): void {
            $message->to($recipient->email)
                ->subject($rendered['subject'])
                ->from($sender->email, $sender->display_name);

            if ($sender->reply_to) {
                $message->replyTo($sender->reply_to);
            }

            foreach ($headers as $name => $value) {
                $message->getHeaders()->addIdHeader($name, trim($value, '<>'));
            }
        });

        return [
            'provider' => 'laravel-mail',
            'provider_message_id' => $headers['Message-ID'] ?? null,
        ];
    }

    /** @param array<string,mixed> $headers @return array<string,string> */
    private function safeDeliveryHeaders(array $headers): array
    {
        $safe = [];

        foreach (self::ALLOWED_DELIVERY_HEADERS as $name) {
            if (! array_key_exists($name, $headers)) {
                continue;
            }

            $value = trim((string) $headers[$name]);
            if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new RuntimeException('invalid_communication_delivery_header');
            }

            $safe[$name] = $value;
        }

        return $safe;
    }
}
