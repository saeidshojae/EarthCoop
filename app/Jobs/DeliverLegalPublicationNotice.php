<?php

namespace App\Jobs;

use App\Mail\LegalPublicationNotice;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class DeliverLegalPublicationNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private const STALE_SENDING_SECONDS = 120;

    public function __construct(
        public readonly int $versionId,
        public readonly int $userId,
        public readonly string $channel,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $delivery = DB::table('legal_publication_deliveries')
            ->where('legal_document_version_id', $this->versionId)
            ->where('user_id', $this->userId)
            ->where('channel', $this->channel)
            ->first();

        if (! $delivery || in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        // Keep the sending lease longer than queue retry_after (90s) so a slow,
        // still-running worker is not reclaimed while it may still send.
        $staleSendingBefore = now()->subSeconds(self::STALE_SENDING_SECONDS);
        $claimed = DB::table('legal_publication_deliveries')
            ->where('id', $delivery->id)
            ->where(function ($query) use ($staleSendingBefore): void {
                $query->whereIn('status', ['queued', 'failed'])
                    ->orWhere(function ($query) use ($staleSendingBefore): void {
                        $query->where('status', 'sending')
                            ->where('updated_at', '<=', $staleSendingBefore);
                    });
            })
            ->update([
                'status' => 'sending',
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        // Prevent duplicate queued jobs from sending the same channel twice.
        if ($claimed !== 1) {
            return;
        }

        try {
            $version = LegalDocumentVersion::with('document')->findOrFail($this->versionId);
            $user = User::findOrFail($this->userId);
            if ($version->status !== 'published' || ! in_array($version->publication_policy, ['notify', 'material'], true)) {
                DB::table('legal_publication_deliveries')->where('id', $delivery->id)
                    ->update(['status' => 'skipped', 'updated_at' => now()]);
                return;
            }

            $documentTitle = $version->document?->title ?? 'اسناد حقوقی';
            $message = $version->publication_policy === 'material'
                ? 'نسخه اساسی جدیدی منتشر شده است؛ لطفاً متن را مطالعه و پذیرش خود را ثبت کنید.'
                : 'نسخه جدیدی از سند حقوقی منتشر شده است؛ پذیرش مجدد لازم نیست.';

            if ($this->channel === 'in_app') {
                Notification::sendNow($user, new GenericNotification(
                    'به‌روزرسانی اسناد حقوقی',
                    $documentTitle . ' — ' . $message,
                    url('/terms'),
                    'legal.publication',
                    ['source' => 'legal_publication', 'version_id' => $version->id, 'policy' => $version->publication_policy]
                ));
            } elseif ($this->channel === 'email') {
                if (blank($user->email)) {
                    DB::table('legal_publication_deliveries')->where('id', $delivery->id)
                        ->update(['status' => 'skipped', 'updated_at' => now()]);
                    return;
                }

                Mail::to($user->email)->send(new LegalPublicationNotice(
                    $version,
                    $documentTitle,
                    $version->publication_policy,
                    url('/terms')
                ));
            } else {
                DB::table('legal_publication_deliveries')->where('id', $delivery->id)
                    ->update(['status' => 'skipped', 'updated_at' => now()]);
                return;
            }

            DB::table('legal_publication_deliveries')->where('id', $delivery->id)->update([
                'status' => 'sent',
                'sent_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('legal_publication_deliveries')->where('id', $delivery->id)->update([
                'status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }
}
