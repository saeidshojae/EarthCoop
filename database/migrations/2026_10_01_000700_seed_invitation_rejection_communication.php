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
            throw new RuntimeException('Canonical management sender identity must exist before invitation rejection communication is seeded.');
        }

        $templateId = DB::table('communication_templates')
            ->where('key', 'auth.invitation_rejected')
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => 'auth.invitation_rejected',
                'name' => 'نتیجه بررسی درخواست کد دعوت',
                'category' => 'auth',
                'classification' => 'required',
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

        DB::table('communication_template_versions')->insert([
            'communication_template_id' => $templateId,
            'version' => 1,
            'locale' => 'fa',
            'subject' => 'درخواست کد دعوت شما بررسی شد',
            'body' => '<div dir="rtl"><p>درخواست شما برای دریافت کد دعوت بررسی و رد شد.</p>{{admin_note_html}}</div>',
            'variables_schema' => json_encode([
                'admin_note_html' => ['type' => 'string', 'required' => true],
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
            ->where('key', 'auth.invitation_rejected')
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
