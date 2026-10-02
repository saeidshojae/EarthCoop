<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $senderId = DB::table('communication_sender_identities')
            ->where('key', 'management')
            ->value('id');

        if ($senderId === null) {
            throw new RuntimeException('Canonical management sender identity must exist before invitation communication is seeded.');
        }

        $templateId = DB::table('communication_templates')
            ->where('key', 'auth.invitation_issued')
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => 'auth.invitation_issued',
                'name' => 'صدور کد دعوت',
                'category' => 'auth',
                'classification' => 'required',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $publishedExists = DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->whereNotNull('published_at')
            ->exists();

        if ($publishedExists) {
            return;
        }

        $nextVersion = ((int) DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->max('version')) + 1;

        DB::table('communication_template_versions')->insert([
            'communication_template_id' => $templateId,
            'version' => max(1, $nextVersion),
            'locale' => 'fa',
            'subject' => 'کد دعوت شما آماده است - Earth Coop',
            'body' => '<div dir="rtl"><h2>کد دعوت شما آماده است!</h2><p>درخواست شما برای پیوستن به Earth Coop تأیید شد.</p><p>کد دعوت: <strong>{{code}}</strong></p><p>این کد تا {{expire_at}} معتبر است.</p><p>با تشکر،<br>تیم Earth Coop</p></div>',
            'variables_schema' => json_encode([
                'code' => ['type' => 'string', 'required' => true],
                'expire_at' => ['type' => 'string', 'required' => true],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'communication_sender_identity_id' => (int) $senderId,
            'published_at' => $now,
            'created_by' => null,
            'approved_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $templateId = DB::table('communication_templates')
            ->where('key', 'auth.invitation_issued')
            ->value('id');

        if ($templateId === null) {
            return;
        }

        DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->delete();
        DB::table('communication_templates')->where('id', $templateId)->delete();
    }
};
