<?php

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\EmailTemplate;
use App\Models\SystemEmail;

class LegacyEmailCommunicationImporter
{
    public function __construct(
        private readonly CommunicationTemplateService $templates,
    ) {}

    /** @return array{senders_imported:int,templates_imported:int} */
    public function import(): array
    {
        $sendersImported = 0;
        $templatesImported = 0;

        foreach (SystemEmail::query()->orderBy('id')->get() as $legacy) {
            $key = $this->senderKey($legacy);
            $existing = CommunicationSenderIdentity::query()
                ->where('key', $key)
                ->orWhere('email', $legacy->email)
                ->first();

            if (! $existing) {
                CommunicationSenderIdentity::query()->create([
                    'key' => $key,
                    'email' => (string) $legacy->email,
                    'display_name' => (string) ($legacy->display_name ?: $legacy->name),
                    'reply_to' => (string) $legacy->email,
                    'purpose' => $legacy->description ?: $legacy->name,
                    'system_identity_key' => in_array($key, ['support', 'management'], true) ? $key : null,
                    'is_active' => (bool) $legacy->is_active,
                    'is_default' => (bool) $legacy->is_default,
                ]);
                $sendersImported++;
            }
        }

        $defaultSender = CommunicationSenderIdentity::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        foreach (EmailTemplate::query()->orderBy('id')->get() as $legacy) {
            $key = 'legacy.email-template.'.(int) $legacy->id;
            $canonical = CommunicationTemplate::query()->where('key', $key)->first();
            if (! $canonical) {
                $canonical = CommunicationTemplate::query()->create([
                    'key' => $key,
                    'name' => (string) $legacy->name,
                    'category' => $legacy->category,
                    'classification' => CommunicationClassification::Operational,
                    'is_active' => (bool) $legacy->is_active,
                ]);
            }

            if (! $canonical->versions()->where('locale', 'fa')->exists()) {
                $this->templates->publish(
                    $canonical,
                    'fa',
                    (string) $legacy->subject,
                    (string) $legacy->body,
                    $this->normalizeLegacyVariables((array) ($legacy->variables ?? [])),
                    $defaultSender,
                );
                $templatesImported++;
            }
        }

        return [
            'senders_imported' => $sendersImported,
            'templates_imported' => $templatesImported,
        ];
    }

    private function senderKey(SystemEmail $legacy): string
    {
        $email = mb_strtolower(trim((string) $legacy->email));
        foreach (['support', 'management'] as $identity) {
            $configured = mb_strtolower(trim((string) config("system-identities.{$identity}.email", '')));
            if ($configured !== '' && hash_equals($configured, $email)) {
                return $identity;
            }
        }

        return 'legacy.system-email.'.(int) $legacy->id;
    }

    /** @return array<string,array{required:bool}> */
    private function normalizeLegacyVariables(array $variables): array
    {
        $normalized = [];
        foreach ($variables as $key => $definition) {
            if (is_int($key)) {
                $name = trim((string) $definition);
                if ($name !== '') {
                    $normalized[$name] = ['required' => true];
                }
                continue;
            }

            $name = trim((string) $key);
            if ($name === '') {
                continue;
            }

            $normalized[$name] = [
                'required' => is_array($definition)
                    ? (bool) ($definition['required'] ?? true)
                    : true,
            ];
        }

        return $normalized;
    }
}
