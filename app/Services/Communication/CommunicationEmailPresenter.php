<?php

namespace App\Services\Communication;

final class CommunicationEmailPresenter
{
    public function present(string $subject, string $body): string
    {
        $body = $this->absolutizeApplicationLinks($body);

        if ($this->isCompleteHtmlDocument($body)) {
            return $body;
        }

        return view('emails.layout', [
            'emailTitle' => $subject,
            'bodyHtml' => $body,
        ])->render();
    }

    private function absolutizeApplicationLinks(string $html): string
    {
        $origin = rtrim((string) config('app.url'), '/');

        if ($origin === '') {
            return $html;
        }

        return preg_replace_callback(
            '/\\b(href|src)=(["\\'])\\/(?!\\/)([^"\\']*)\\2/i',
            static fn (array $match): string => $match[1].'='.$match[2].$origin.'/'.$match[3].$match[2],
            $html,
        ) ?? $html;
    }

    private function isCompleteHtmlDocument(string $body): bool
    {
        return preg_match('/<(?:!doctype\s+html|html)\b/i', $body) === 1;
    }
}
