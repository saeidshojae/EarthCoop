<?php

namespace App\Mail;

use App\Models\LegalDocumentVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LegalPublicationNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LegalDocumentVersion $version,
        public readonly string $documentTitle,
        public readonly string $policy,
        public readonly string $termsUrl,
    ) {
    }

    public function build()
    {
        $subject = $this->policy === 'material'
            ? 'اقدام لازم: نسخه جدید اسناد حقوقی EarthCoop'
            : 'اطلاع‌رسانی: به‌روزرسانی اسناد حقوقی EarthCoop';

        return $this->subject($subject)
            ->view('emails.legal-publication-notice');
    }
}
