<?php

namespace App\Services\Communication;

use App\Models\CommunicationTemplateVersion;
use InvalidArgumentException;

class CommunicationTemplateRenderer
{
    /** @return array{subject:string,body:string} */
    public function render(CommunicationTemplateVersion $version, array $context): array
    {
        $schema = (array) ($version->variables_schema ?? []);
        $declared = array_keys($schema);
        $unknown = array_values(array_diff(array_keys($context), $declared));

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown template variables: '.implode(', ', $unknown));
        }

        $missing = [];
        foreach ($schema as $key => $definition) {
            $required = (bool) data_get($definition, 'required', false);
            if ($required && (! array_key_exists($key, $context) || $context[$key] === null)) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing required template variables: '.implode(', ', $missing));
        }

        $subject = (string) $version->subject;
        $body = (string) $version->body;
        foreach ($context as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException("Template variable {$key} must be scalar or null.");
            }

            $replacement = $value === null ? '' : (string) $value;
            $pattern = '/\{\{\s*'.preg_quote((string) $key, '/').'\s*\}\}/';
            $subject = preg_replace($pattern, $replacement, $subject) ?? $subject;
            $body = preg_replace($pattern, $replacement, $body) ?? $body;
        }

        if (preg_match('/\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}/', $subject.' '.$body) === 1) {
            throw new InvalidArgumentException('Rendered template still contains unresolved variables.');
        }

        return ['subject' => $subject, 'body' => $body];
    }
}
