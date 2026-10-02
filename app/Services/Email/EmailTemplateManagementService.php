<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use App\Services\Communication\LegacyEmailTemplateCanonicalSyncService;
use Illuminate\Support\Arr;

class EmailTemplateManagementService
{
    public function __construct(
        private readonly LegacyEmailTemplateCanonicalSyncService $canonicalSync,
    ) {}

    /** @param array<string,mixed> $attributes */
    public function update(EmailTemplate $template, array $attributes): EmailTemplate
    {
        $allowed = Arr::only($attributes, [
            'name', 'subject', 'body', 'variables', 'category', 'description', 'is_active',
        ]);

        $template->fill($allowed);
        $template->save();
        $template = $template->refresh();

        $this->canonicalSync->sync($template);

        return $template;
    }
}
