<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\Communication;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\EmailTemplate;
use App\Models\SystemEmail;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AdminEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_template_send_is_imported_and_queued_through_canonical_engine(): void
    {
        Queue::fake();
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->legacyDefaultSender();

        $legacy = EmailTemplate::query()->create([
            'name' => 'Legacy notice',
            'subject' => 'سلام {{name}}',
            'body' => '<p>{{name}}</p>',
            'variables' => ['name'],
            'category' => 'legacy',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/admin/emails/send-template', [
                'template_id' => $legacy->id,
                'recipients' => ['legacy-guest@example.test'],
                'variables' => ['name' => 'مهمان'],
            ])
            ->assertRedirect('/admin/emails/send');

        $this->assertDatabaseHas('communication_templates', [
            'key' => 'legacy.email-template.'.$legacy->id,
        ]);
        $this->assertDatabaseHas('communication_recipients', [
            'email' => 'legacy-guest@example.test',
            'user_id' => null,
        ]);
        $this->assertSame(1, Communication::query()->where('source_type', 'admin.manual_template')->count());
        Mail::assertNothingSent();
    }

    public function test_custom_admin_send_uses_canonical_manual_template_and_external_recipients(): void
    {
        Queue::fake();
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $sender = $this->legacyDefaultSender();
        $this->canonicalCustomTemplate($sender);

        $this->actingAs($admin)
            ->post('/admin/emails/send-custom', [
                'recipients' => ['custom-a@example.test', 'custom-b@example.test'],
                'subject' => 'Custom subject',
                'body' => '<p>Custom body</p>',
            ])
            ->assertRedirect('/admin/emails/send');

        $communication = Communication::query()->where('source_type', 'admin.manual_custom')->firstOrFail();
        $this->assertSame(2, $communication->recipients()->count());
        $this->assertSame(
            ['custom-a@example.test', 'custom-b@example.test'],
            $communication->recipients()->orderBy('email')->pluck('email')->all(),
        );
        Mail::assertNothingSent();
    }

    private function legacyDefaultSender(): CommunicationSenderIdentity
    {
        SystemEmail::query()->create([
            'name' => 'Default Mail',
            'email' => 'management@earthcoop.ir',
            'display_name' => 'EarthCoop Management',
            'description' => 'Default admin sender',
            'is_active' => true,
            'is_default' => true,
        ]);

        return CommunicationSenderIdentity::query()->firstOrCreate(
            ['key' => 'management'],
            [
                'email' => 'management@earthcoop.ir',
                'display_name' => 'EarthCoop Management',
                'reply_to' => 'management@earthcoop.ir',
                'system_identity_key' => 'management',
                'is_active' => true,
                'is_default' => true,
            ],
        );
    }

    private function canonicalCustomTemplate(CommunicationSenderIdentity $sender): void
    {
        $template = CommunicationTemplate::query()->firstOrCreate(
            ['key' => 'admin.manual_custom'],
            [
                'name' => 'ارسال دستی سفارشی',
                'category' => 'admin',
                'classification' => CommunicationClassification::Operational,
                'is_active' => true,
            ],
        );

        if (! $template->versions()->where('locale', 'fa')->whereNotNull('published_at')->exists()) {
            app(CommunicationTemplateService::class)->publish(
                $template,
                'fa',
                '{{subject}}',
                '{{rendered_html}}',
                [
                    'subject' => ['type' => 'string', 'required' => true],
                    'rendered_html' => ['type' => 'string', 'required' => true],
                ],
                $sender,
            );
        }
    }
}
