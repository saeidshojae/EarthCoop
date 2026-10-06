<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $supportSenderId = (int) DB::table('communication_sender_identities')
            ->where('key', 'support')
            ->value('id');

        $managementSenderId = (int) DB::table('communication_sender_identities')
            ->where('key', 'management')
            ->value('id');

        if ($supportSenderId <= 0 || $managementSenderId <= 0) {
            throw new RuntimeException('communication_closure_sender_identity_missing');
        }

        $this->template(
            key: 'support.ticket_internal_alert',
            name: 'هشدار داخلی تیکت پشتیبانی',
            category: 'support',
            subject: '{{subject}}',
            body: '{{rendered_html}}',
            schema: [
                'subject' => ['type' => 'string', 'required' => true],
                'rendered_html' => ['type' => 'string', 'required' => true],
            ],
            senderId: $supportSenderId,
            now: $now,
        );

        $this->template(
            key: 'najm_bahar.project_assigned',
            name: 'ارجاع پروژه نجم بهار',
            category: 'najm_bahar',
            subject: 'ارجاع پروژه برای بررسی: {{project_title}}',
            body: '<p>سلام {{recipient_name}}</p>'
                .'<p>یک پروژه برای بررسی و ارزیابی به شما ارجاع شده است.</p>'
                .'<p><strong>نام پروژه:</strong> {{project_title}}</p>'
                .'<p><strong>دسته‌بندی:</strong> {{category}}</p>'
                .'<p><strong>سرمایه مورد نیاز:</strong> {{required_capital}}</p>'
                .'<p><strong>توضیحات ارجاع:</strong> {{assignment_note}}</p>'
                .'<p><a href="{{project_url}}">مشاهده جزئیات پروژه</a></p>',
            schema: [
                'recipient_name' => ['type' => 'string', 'required' => true],
                'project_title' => ['type' => 'string', 'required' => true],
                'category' => ['type' => 'string', 'required' => true],
                'required_capital' => ['type' => 'string', 'required' => true],
                'assignment_note' => ['type' => 'string', 'required' => true],
                'project_url' => ['type' => 'string', 'required' => true],
            ],
            senderId: $managementSenderId,
            now: $now,
        );
    }

    public function down(): void
    {
        $keys = [
            'support.ticket_internal_alert',
            'najm_bahar.project_assigned',
        ];

        $templateIds = DB::table('communication_templates')
            ->whereIn('key', $keys)
            ->pluck('id');

        if ($templateIds->isEmpty()) {
            return;
        }

        DB::table('communication_template_versions')
            ->whereIn('communication_template_id', $templateIds)
            ->delete();

        DB::table('communication_templates')
            ->whereIn('id', $templateIds)
            ->delete();
    }

    /** @param array<string,array<string,mixed>> $schema */
    private function template(
        string $key,
        string $name,
        string $category,
        string $subject,
        string $body,
        array $schema,
        int $senderId,
        $now,
    ): void {
        $templateId = DB::table('communication_templates')
            ->where('key', $key)
            ->value('id');

        if ($templateId === null) {
            $templateId = DB::table('communication_templates')->insertGetId([
                'key' => $key,
                'name' => $name,
                'category' => $category,
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
            'subject' => $subject,
            'body' => $body,
            'variables_schema' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'communication_sender_identity_id' => $senderId,
            'published_at' => $now,
            'created_by' => null,
            'approved_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
