<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LowRiskCanonicalDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_sender_and_low_risk_templates_are_installed_by_migrations(): void
    {
        $support = CommunicationSenderIdentity::query()->where('key', 'support')->firstOrFail();
        $this->assertTrue($support->is_active);
        $this->assertNotSame('', trim((string) $support->email));

        foreach ([
            'support.ticket_reply',
            'support.ticket_created',
            'faq.answer',
        ] as $key) {
            $template = CommunicationTemplate::query()->where('key', $key)->firstOrFail();
            $this->assertSame(CommunicationClassification::Operational, $template->classification);
            $this->assertTrue($template->is_active);
            $this->assertTrue($template->versions()->where('locale', 'fa')->whereNotNull('published_at')->exists());
        }
    }

    public function test_management_sender_and_manual_custom_template_are_installed_by_migrations(): void
    {
        $management = CommunicationSenderIdentity::query()->where('key', 'management')->firstOrFail();
        $this->assertTrue($management->is_active);

        $template = CommunicationTemplate::query()->where('key', 'admin.manual_custom')->firstOrFail();
        $this->assertSame(CommunicationClassification::Operational, $template->classification);
        $this->assertTrue($template->is_active);
        $this->assertTrue($template->versions()->where('locale', 'fa')->whereNotNull('published_at')->exists());
        $this->assertSame(
            $management->id,
            $template->versions()->where('locale', 'fa')->latest('version')->firstOrFail()->communication_sender_identity_id,
        );
    }
}
