<?php

namespace App\Services\Communication;

use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommunicationTemplateService
{
    /**
     * @param array<string,array<string,mixed>> $variablesSchema
     */
    public function publish(
        CommunicationTemplate $template,
        string $locale,
        string $subject,
        string $body,
        array $variablesSchema,
        ?CommunicationSenderIdentity $sender = null,
        ?int $createdBy = null,
        ?int $approvedBy = null,
    ): CommunicationTemplateVersion {
        $locale = trim($locale);
        if ($locale === '') {
            throw new InvalidArgumentException('Template locale is required.');
        }

        $this->assertVariableContract($subject, $body, $variablesSchema);

        return DB::transaction(function () use (
            $template,
            $locale,
            $subject,
            $body,
            $variablesSchema,
            $sender,
            $createdBy,
            $approvedBy,
        ): CommunicationTemplateVersion {
            $latestVersion = CommunicationTemplateVersion::query()
                ->where('communication_template_id', $template->id)
                ->where('locale', $locale)
                ->lockForUpdate()
                ->max('version');

            try {
                return CommunicationTemplateVersion::query()->create([
                    'communication_template_id' => $template->id,
                    'version' => ((int) $latestVersion) + 1,
                    'locale' => $locale,
                    'subject' => $subject,
                    'body' => $body,
                    'variables_schema' => $variablesSchema,
                    'communication_sender_identity_id' => $sender?->id,
                    'published_at' => now(),
                    'created_by' => $createdBy,
                    'approved_by' => $approvedBy,
                ]);
            } catch (QueryException $exception) {
                throw $exception;
            }
        });
    }

    /** @param array<string,array<string,mixed>> $variablesSchema */
    private function assertVariableContract(string $subject, string $body, array $variablesSchema): void
    {
        preg_match_all('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', $subject.' '.$body, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        $declared = array_keys($variablesSchema);

        $undeclared = array_values(array_diff($placeholders, $declared));
        if ($undeclared !== []) {
            throw new InvalidArgumentException(
                'Template contains undeclared variables: '.implode(', ', $undeclared)
            );
        }
    }
}
