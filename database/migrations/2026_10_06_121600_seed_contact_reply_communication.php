<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $senderId = DB::table('communication_sender_identities')
            ->where('key', 'support')
            ->value('id');

        if ($senderId === null) {
            return;
        }

        $now = now();
        $templateId = DB::table('communication_templates')
            ->where('key', 'contact.reply')
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => 'contact.reply',
                'name' => 'پاسخ پیام تماس',
                'category' => 'support',
                'classification' => 'operational',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $exists = DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->whereNotNull('published_at')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('communication_template_versions')->insert([
            'communication_template_id' => $templateId,
            'version' => 1,
            'locale' => 'fa',
            'subject' => 'پاسخ EarthCoop: {{subject}}',
            'body' => '{{rendered_html}}',
            'variables_schema' => json_encode([
                'subject' => ['type' => 'string', 'required' => true],
                'rendered_html' => ['type' => 'string', 'required' => true],
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
            ->where('key', 'contact.reply')
            ->value('id');

        if ($templateId === null) {
            return;
        }

        DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->delete();

        DB::table('communication_templates')
            ->where('id', $templateId)
            ->delete();
    }
};
