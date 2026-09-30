<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $senderId = DB::table('communication_sender_identities')
            ->where('key', 'onboarding')
            ->value('id');

        if ($senderId === null) {
            $senderId = DB::table('communication_sender_identities')->insertGetId([
                'key' => 'onboarding',
                'email' => 'welcome@earthcoop.ir',
                'display_name' => 'EarthCoop',
                'reply_to' => null,
                'purpose' => 'Member onboarding communications',
                'system_identity_key' => null,
                'is_active' => true,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $templateId = DB::table('communication_templates')
            ->where('key', 'onboarding.welcome')
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => 'onboarding.welcome',
                'name' => 'Welcome',
                'category' => 'onboarding',
                'classification' => 'operational',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $versionExists = DB::table('communication_template_versions')
            ->where('communication_template_id', $templateId)
            ->where('locale', 'fa')
            ->where('version', 1)
            ->exists();

        if (! $versionExists) {
            DB::table('communication_template_versions')->insert([
                'communication_template_id' => $templateId,
                'version' => 1,
                'locale' => 'fa',
                'subject' => 'به ارث‌کوپ خوش آمدید {{display_name}}',
                'body' => '<p>{{display_name}} عزیز، عضویت شما کامل شد.</p>',
                'variables_schema' => json_encode([
                    'display_name' => ['type' => 'string', 'required' => true],
                    'email' => ['type' => 'string', 'required' => true],
                    'profile_url' => ['type' => 'string', 'required' => true],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'communication_sender_identity_id' => $senderId,
                'published_at' => $now,
                'created_by' => null,
                'approved_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('communication_rules')->where('key', 'onboarding.welcome.rule')->exists()) {
            DB::table('communication_rules')->insert([
                'key' => 'onboarding.welcome.rule',
                'name' => 'Welcome after registration',
                'trigger_type' => 'event',
                'event_key' => 'registration.completed',
                'condition_definition' => null,
                'audience_definition' => json_encode(
                    ['key' => 'event.user'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'communication_template_id' => $templateId,
                'communication_sender_identity_id' => $senderId,
                'classification' => 'operational',
                'priority' => 2,
                'delay_seconds' => 0,
                'is_active' => true,
                'created_by' => null,
                'approved_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('communication_rules')
            ->where('key', 'onboarding.welcome.rule')
            ->delete();

        $templateId = DB::table('communication_templates')
            ->where('key', 'onboarding.welcome')
            ->value('id');

        if ($templateId !== null) {
            DB::table('communication_template_versions')
                ->where('communication_template_id', $templateId)
                ->where('locale', 'fa')
                ->where('version', 1)
                ->delete();

            DB::table('communication_templates')
                ->where('id', $templateId)
                ->delete();
        }

        DB::table('communication_sender_identities')
            ->where('key', 'onboarding')
            ->where('email', 'welcome@earthcoop.ir')
            ->delete();
    }
};
