<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationSenderIdentity;
use App\Models\EmailTemplate;
use App\Models\SystemEmail;
use App\Services\Communication\LegacyEmailCommunicationImporter;
use App\Services\Communication\SenderIdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SenderIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_sender_resolver_returns_canonical_presentation_identity_without_provider_secrets(): void
    {
        config([
            'mail.mailers.smtp.username' => 'secret-user',
            'mail.mailers.smtp.password' => 'secret-password',
        ]);

        CommunicationSenderIdentity::query()->create([
            'key' => 'support',
            'email' => 'support@earthcoop.ir',
            'display_name' => 'تیم پشتیبانی EarthCoop',
            'reply_to' => 'support@earthcoop.ir',
            'purpose' => 'support',
            'system_identity_key' => 'support',
            'is_active' => true,
        ]);

        $resolved = app(SenderIdentityResolver::class)->resolve('support');

        $this->assertInstanceOf(CommunicationSenderIdentity::class, $resolved);
        $this->assertSame('support@earthcoop.ir', $resolved->email);
        $this->assertSame('تیم پشتیبانی EarthCoop', $resolved->display_name);
        $this->assertSame('support@earthcoop.ir', $resolved->reply_to);
        $this->assertArrayNotHasKey('username', $resolved->getAttributes());
        $this->assertArrayNotHasKey('password', $resolved->getAttributes());
        $this->assertStringNotContainsString('secret', json_encode($resolved->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function test_sender_resolver_rejects_inactive_or_unknown_sender(): void
    {
        CommunicationSenderIdentity::query()->create([
            'key' => 'disabled',
            'email' => 'disabled@earthcoop.ir',
            'display_name' => 'Disabled',
            'is_active' => false,
        ]);

        foreach (['disabled', 'missing'] as $key) {
            try {
                app(SenderIdentityResolver::class)->resolve($key);
                $this->fail("Sender {$key} must not resolve.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($key, $e->getMessage());
            }
        }
    }

    public function test_legacy_system_emails_and_templates_can_be_imported_without_deleting_or_mutating_source_rows(): void
    {
        $systemEmail = SystemEmail::query()->create([
            'name' => 'Support',
            'email' => 'support@earthcoop.ir',
            'display_name' => 'EarthCoop Support',
            'description' => 'Legacy support sender',
            'is_active' => true,
            'is_default' => true,
        ]);
        $legacyTemplate = EmailTemplate::query()->create([
            'name' => 'Legacy Welcome',
            'subject' => 'سلام {{name}}',
            'body' => '<p>خوش آمدید {{name}}</p>',
            'variables' => ['name'],
            'category' => 'onboarding',
            'is_active' => true,
            'description' => 'Legacy template',
        ]);

        $result = app(LegacyEmailCommunicationImporter::class)->import();

        $this->assertSame(1, $result['senders_imported']);
        $this->assertSame(1, $result['templates_imported']);
        $this->assertDatabaseHas('communication_sender_identities', [
            'email' => 'support@earthcoop.ir',
            'display_name' => 'EarthCoop Support',
            'is_active' => true,
            'is_default' => true,
        ]);

        $canonicalTemplate = \App\Models\CommunicationTemplate::query()
            ->where('key', 'legacy.email-template.'.$legacyTemplate->id)
            ->firstOrFail();
        $this->assertSame('Legacy Welcome', $canonicalTemplate->name);
        $version = $canonicalTemplate->versions()->firstOrFail();
        $this->assertSame('fa', $version->locale);
        $this->assertSame('سلام {{name}}', $version->subject);
        $this->assertSame(['name' => ['required' => true]], $version->variables_schema);

        $this->assertDatabaseHas('system_emails', ['id' => $systemEmail->id, 'email' => 'support@earthcoop.ir']);
        $this->assertDatabaseHas('email_templates', ['id' => $legacyTemplate->id, 'subject' => 'سلام {{name}}']);

        $again = app(LegacyEmailCommunicationImporter::class)->import();
        $this->assertSame(0, $again['senders_imported']);
        $this->assertSame(0, $again['templates_imported']);
        $this->assertSame(1, CommunicationSenderIdentity::query()->where('email', 'support@earthcoop.ir')->count());
        $this->assertSame(1, $canonicalTemplate->fresh()->versions()->count());
    }
}
