<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $supportId = $this->sender(
            key: 'support',
            email: 'support@earthcoop.ir',
            displayName: 'تیم پشتیبانی EarthCoop',
            replyTo: 'support@earthcoop.ir',
            purpose: 'Support and service communications',
            systemIdentityKey: 'support',
            isDefault: false,
            now: $now,
        );

        $managementId = $this->sender(
            key: 'management',
            email: 'management@earthcoop.ir',
            displayName: 'EarthCoop Management',
            replyTo: 'management@earthcoop.ir',
            purpose: 'Administrative communications',
            systemIdentityKey: 'management',
            isDefault: true,
            now: $now,
        );

        $this->template(
            key: 'support.ticket_reply',
            name: 'پاسخ تیکت پشتیبانی',
            category: 'support',
            subject: '{{subject}}',
            body: '{{rendered_html}}',
            schema: [
                'subject' => ['type' => 'string', 'required' => true],
                'rendered_html' => ['type' => 'string', 'required' => true],
                '_delivery_headers' => ['type' => 'array', 'required' => false],
            ],
            senderId: $supportId,
            now: $now,
        );

        $this->template(
            key: 'support.ticket_created',
            name: 'ایجاد تیکت پشتیبانی',
            category: 'support',
            subject: '{{subject}}',
            body: '{{rendered_html}}',
            schema: [
                'subject' => ['type' => 'string', 'required' => true],
                'rendered_html' => ['type' => 'string', 'required' => true],
            ],
            senderId: $supportId,
            now: $now,
        );

        $this->template(
            key: 'faq.answer',
            name: 'پاسخ پرسش متداول',
            category: 'faq',
            subject: 'پاسخ به پرسش: {{title}}',
            body: '<p>{{answer}}</p>',
            schema: [
                'title' => ['type' => 'string', 'required' => true],
                'answer' => ['type' => 'string', 'required' => true],
            ],
            senderId: $supportId,
            now: $now,
        );

        $this->template(
            key: 'admin.manual_custom',
            name: 'ارسال دستی سفارشی',
            category: 'admin',
            subject: '{{subject}}',
            body: '{{rendered_html}}',
            schema: [
                'subject' => ['type' => 'string', 'required' => true],
                'rendered_html' => ['type' => 'string', 'required' => true],
            ],
            senderId: $managementId,
            now: $now,
        );
    }

    public function down(): void
    {
        $keys = [
            'support.ticket_reply',
            'support.ticket_created',
            'faq.answer',
            'admin.manual_custom',
        ];

        $templateIds = DB::table('communication_templates')
            ->whereIn('key', $keys)
            ->pluck('id');

        if ($templateIds->isNotEmpty()) {
            DB::table('communication_template_versions')
                ->whereIn('communication_template_id', $templateIds)
                ->delete();

            DB::table('communication_templates')
                ->whereIn('id', $templateIds)
                ->delete();
        }

        DB::table('communication_sender_identities')
            ->whereIn('key', ['support', 'management'])
            ->delete();
    }

    private function sender(
        string $key,
        string $email,
        string $displayName,
        ?string $replyTo,
        string $purpose,
        ?string $systemIdentityKey,
        bool $isDefault,
        $now,
    ): int {
        $existing = DB::table('communication_sender_identities')
            ->where('key', $key)
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('communication_sender_identities')->insertGetId([
            'key' => $key,
            'email' => $email,
            'display_name' => $displayName,
            'reply_to' => $replyTo,
            'purpose' => $purpose,
            'system_identity_key' => $systemIdentityKey,
            'is_active' => true,
            'is_default' => $isDefault,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
            'variables_schema' => json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
            'communication_sender_identity_id' => $senderId,
            'published_at' => $now,
            'created_by' => null,
            'approved_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
