<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('communication_sender_identities')
            ->where('key', 'support')
            ->update([
                'display_name' => 'تیم پشتیبانی EarthCoop',
                'purpose' => 'ارتباطات پشتیبانی و خدمات',
                'updated_at' => $now,
            ]);

        DB::table('communication_sender_identities')
            ->where('key', 'management')
            ->update([
                'display_name' => 'مدیریت EarthCoop',
                'purpose' => 'ارتباطات مدیریتی و اداری',
                'updated_at' => $now,
            ]);

        $senderId = DB::table('communication_sender_identities')
            ->where('key', 'management')
            ->value('id');

        if ($senderId === null) {
            $senderId = DB::table('communication_sender_identities')->insertGetId([
                'key' => 'management',
                'email' => 'management@earthcoop.ir',
                'display_name' => 'مدیریت EarthCoop',
                'reply_to' => 'management@earthcoop.ir',
                'purpose' => 'ارتباطات مدیریتی و اداری',
                'system_identity_key' => 'management',
                'is_active' => true,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $templateId = DB::table('communication_templates')
            ->where('key', 'system.scheduled_smoke_test')
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => 'system.scheduled_smoke_test',
                'name' => 'تست سلامت زمان‌بندی مرکز ارتباطات',
                'category' => 'system',
                'classification' => 'operational',
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
            'subject' => 'تست زمان‌بندی مرکز ارتباطات',
            'body' => '<p>این پیام فقط برای تست سلامت زمان‌بندی و صف ارتباطات EarthCoop ارسال شده است.</p><p>در صورت دریافت این پیام، مسیر زمان‌بندی، صف، پردازشگر و ارسال ایمیل با موفقیت تا این مرحله طی شده است.</p>',
            'variables_schema' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'communication_sender_identity_id' => $senderId,
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
            ->where('key', 'system.scheduled_smoke_test')
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
