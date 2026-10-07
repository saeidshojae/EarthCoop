<?php

namespace App\Services\Communication;

final class CommunicationEmailPresenter
{
    public function present(string $subject, string $body): string
    {
        if ($this->isCompleteHtmlDocument($body)) {
            return $body;
        }

        return view('emails.layout', [
            'emailTitle' => $subject,
            'bodyHtml' => $body,
        ])->render();
    }

    private function isCompleteHtmlDocument(string $body): bool
    {
        return preg_match('/<(?:!doctype\s+html|html)\b/i', $body) === 1;
    }
}
