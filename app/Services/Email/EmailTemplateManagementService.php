<?php

namespace App\Services\Email;

use App\Models\EmailTemplate;
use Illuminate\Support\Arr;

class EmailTemplateManagementService
{
    /** @param array<string,mixed> $attributes */
    public function update(EmailTemplate $template, array $attributes): EmailTemplate
    {
        $allowed = Arr::only($attributes, [
            'name', 'subject', 'body', 'variables', 'category', 'description', 'is_active',
        ]);

        $template->fill($allowed);
        $template->save();

        return $template->refresh();
    }
}
