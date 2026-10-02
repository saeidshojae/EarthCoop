<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $senderId = DB::table('communication_sender_identities')
            ->where('key', 'reports')
            ->value('id');

        if ($senderId === null) {
            $senderId = DB::table('communication_sender_identities')->insertGetId([
                'key' => 'reports',
                'email' => 'reports@earthcoop.ir',
                'display_name' => 'EarthCoop',
                'reply_to' => null,
                'purpose' => 'Role-aware operational reports',
                'system_identity_key' => null,
                'is_active' => true,
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $reports = [
            'reports.member.weekly' => [
                'name' => 'Weekly member report',
                'audience' => 'role.member',
                'subject' => 'گزارش هفتگی ارث‌کوپ — {{display_name}}',
                'body' => '<p>{{display_name}} عزیز، این خلاصه هفتگی شما از {{period_start}} تا {{period_end}} است.</p><p>گروه‌های فعال: {{groups_count}} | انتخابات باز: {{open_elections_count}} | نظرسنجی‌های باز: {{open_polls_count}} | اعلان‌های خوانده‌نشده: {{unread_notifications_count}}</p>',
                'variables' => $this->memberVariables(),
            ],
            'reports.manager.weekly' => [
                'name' => 'Weekly manager report',
                'audience' => 'role.manager',
                'subject' => 'گزارش هفتگی مدیریتی ارث‌کوپ — {{display_name}}',
                'body' => '<p>{{display_name}} عزیز، این خلاصه هفتگی شما از {{period_start}} تا {{period_end}} است.</p><p>گروه‌های شخصی: {{groups_count}} | انتخابات باز: {{open_elections_count}} | نظرسنجی‌های باز: {{open_polls_count}} | اعلان‌های خوانده‌نشده: {{unread_notifications_count}}</p><p>گروه‌های تحت مدیریت: {{managed_groups_count}} | انتخابات باز مدیریتی: {{open_elections_in_managed_groups_count}} | نظرسنجی‌های باز مدیریتی: {{open_polls_in_managed_groups_count}}</p>',
                'variables' => array_merge($this->memberVariables(), [
                    'managed_groups_count' => ['type' => 'integer', 'required' => true],
                    'managed_group_ids' => ['type' => 'array', 'required' => true],
                    'open_elections_in_managed_groups_count' => ['type' => 'integer', 'required' => true],
                    'open_polls_in_managed_groups_count' => ['type' => 'integer', 'required' => true],
                ]),
            ],
            'reports.inspector.weekly' => [
                'name' => 'Weekly inspector report',
                'audience' => 'role.inspector',
                'subject' => 'گزارش هفتگی بازرسی ارث‌کوپ — {{display_name}}',
                'body' => '<p>{{display_name}} عزیز، این خلاصه هفتگی شما از {{period_start}} تا {{period_end}} است.</p><p>گروه‌های شخصی: {{groups_count}} | انتخابات باز: {{open_elections_count}} | نظرسنجی‌های باز: {{open_polls_count}} | اعلان‌های خوانده‌نشده: {{unread_notifications_count}}</p><p>گروه‌های تحت بازرسی: {{inspected_groups_count}} | انتخابات باز بازرسی: {{open_elections_in_inspected_groups_count}} | نظرسنجی‌های باز بازرسی: {{open_polls_in_inspected_groups_count}}</p>',
                'variables' => array_merge($this->memberVariables(), [
                    'inspected_groups_count' => ['type' => 'integer', 'required' => true],
                    'inspected_group_ids' => ['type' => 'array', 'required' => true],
                    'open_elections_in_inspected_groups_count' => ['type' => 'integer', 'required' => true],
                    'open_polls_in_inspected_groups_count' => ['type' => 'integer', 'required' => true],
                ]),
            ],
        ];

        foreach ($reports as $key => $report) {
            $templateId = DB::table('communication_templates')->where('key', $key)->value('id');
            if ($templateId === null) {
                $templateId = DB::table('communication_templates')->insertGetId([
                    'key' => $key,
                    'name' => $report['name'],
                    'category' => 'reports',
                    'classification' => 'operational',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! DB::table('communication_template_versions')
                ->where('communication_template_id', $templateId)
                ->where('locale', 'fa')
                ->where('version', 1)
                ->exists()) {
                DB::table('communication_template_versions')->insert([
                    'communication_template_id' => $templateId,
                    'version' => 1,
                    'locale' => 'fa',
                    'subject' => $report['subject'],
                    'body' => $report['body'],
                    'variables_schema' => json_encode($report['variables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'communication_sender_identity_id' => $senderId,
                    'published_at' => $now,
                    'created_by' => null,
                    'approved_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ruleKey = $key.'.rule';
            $ruleId = DB::table('communication_rules')->where('key', $ruleKey)->value('id');
            if ($ruleId === null) {
                $ruleId = DB::table('communication_rules')->insertGetId([
                    'key' => $ruleKey,
                    'name' => $report['name'],
                    'trigger_type' => 'scheduled',
                    'event_key' => null,
                    'condition_definition' => null,
                    'audience_definition' => json_encode(['key' => $report['audience']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

            if (! DB::table('communication_rule_schedules')->where('communication_rule_id', $ruleId)->exists()) {
                DB::table('communication_rule_schedules')->insert([
                    'communication_rule_id' => $ruleId,
                    'frequency' => 'weekly',
                    'schedule_definition' => json_encode(['interval' => 1]),
                    'timezone' => (string) config('app.timezone', 'Asia/Tehran'),
                    'timezone_mode' => 'system',
                    'next_run_at' => $now->copy()->addWeek(),
                    'last_run_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'reports.member.weekly',
            'reports.manager.weekly',
            'reports.inspector.weekly',
        ];

        $ruleIds = DB::table('communication_rules')
            ->whereIn('key', array_map(fn (string $key): string => $key.'.rule', $keys))
            ->pluck('id');

        if ($ruleIds->isNotEmpty()) {
            DB::table('communication_rule_schedules')->whereIn('communication_rule_id', $ruleIds)->delete();
            DB::table('communication_rules')->whereIn('id', $ruleIds)->delete();
        }

        $templateIds = DB::table('communication_templates')->whereIn('key', $keys)->pluck('id');
        if ($templateIds->isNotEmpty()) {
            DB::table('communication_template_versions')->whereIn('communication_template_id', $templateIds)->delete();
            DB::table('communication_templates')->whereIn('id', $templateIds)->delete();
        }

        DB::table('communication_sender_identities')
            ->where('key', 'reports')
            ->where('email', 'reports@earthcoop.ir')
            ->delete();
    }

    /** @return array<string,array{type:string,required:bool}> */
    private function memberVariables(): array
    {
        return [
            'period_start' => ['type' => 'string', 'required' => true],
            'period_end' => ['type' => 'string', 'required' => true],
            'display_name' => ['type' => 'string', 'required' => true],
            'groups_count' => ['type' => 'integer', 'required' => true],
            'open_elections_count' => ['type' => 'integer', 'required' => true],
            'open_polls_count' => ['type' => 'integer', 'required' => true],
            'unread_notifications_count' => ['type' => 'integer', 'required' => true],
        ];
    }
};
