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
            throw new RuntimeException('Canonical management sender identity must exist before member invitation communication is seeded.');
        }

        $templateId = DB::table('communication_templates')
            ->where('key', 'membership.member_invitation')
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => 'membership.member_invitation',
                'name' => 'دعوت عضو به EarthCoop',
                'category' => 'membership',
                'classification' => 'operational',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->whereNotNull('published_at')
            ->exists()) {
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
            'subject' => 'دعوت برای پیوستن به EarthCoop',
            'body' => '<div dir="rtl"><h2>دعوت برای پیوستن به EarthCoop</h2><p>برای پیوستن به EarthCoop از کد دعوت زیر استفاده کنید:</p><p><strong>{{code}}</strong></p><p>این کد تا {{expire_at}} معتبر است.</p></div>',
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
            ->where('key', 'membership.member_invitation')
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
