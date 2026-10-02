<?php

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\EmailTemplate;

class LegacyEmailTemplateCanonicalSyncService
{
    public function __construct(
        private readonly CommunicationTemplateService $templates,
    ) {}

    public function sync(EmailTemplate $legacy): ?CommunicationTemplateVersion
    {
        $canonical = CommunicationTemplate::query()->firstOrCreate(
            ['key' => $this->canonicalKey($legacy)],
            [
                'name' => (string) $legacy->name,
                'category' => $legacy->category,
                'classification' => CommunicationClassification::Operational,
                'is_active' => (bool) $legacy->is_active,
            ],
        );

        $canonical->fill([
            'name' => (string) $legacy->name,
            'category' => $legacy->category,
            'is_active' => (bool) $legacy->is_active,
        ]);
        if ($canonical->isDirty()) {
            $canonical->save();
        }

        $variablesSchema = $this->normalizeLegacyVariables((array) ($legacy->variables ?? []));
        $sender = $this->defaultSender();
        $latest = $canonical->versions()
            ->where('locale', 'fa')
            ->orderByDesc('version')
            ->first();

        if ($latest && $this->matchesLegacyState($latest, $legacy, $variablesSchema, $sender)) {
            return $latest;
        }

        return $this->templates->publish(
            $canonical,
            'fa',
            (string) $legacy->subject,
            (string) $legacy->body,
            $variablesSchema,
            $sender,
        );
    }

    private function canonicalKey(EmailTemplate $legacy): string
    {
        return 'legacy.email-template.'.(int) $legacy->id;
    }

    private function defaultSender(): ?CommunicationSenderIdentity
    {
        return CommunicationSenderIdentity::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /** @param array<string,array{required:bool}> $variablesSchema */
    private function matchesLegacyState(
        CommunicationTemplateVersion $latest,
        EmailTemplate $legacy,
        array $variablesSchema,
        ?CommunicationSenderIdentity $sender,
    ): bool {
        return (string) $latest->subject === (string) $legacy->subject
            && (string) $latest->body === (string) $legacy->body
            && (array) ($latest->variables_schema ?? []) === $variablesSchema
            && (int) ($latest->communication_sender_identity_id ?? 0) === (int) ($sender?->id ?? 0);
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
