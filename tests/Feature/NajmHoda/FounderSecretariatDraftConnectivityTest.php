<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderSecretariatDraftConnectivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_correspondence_creates_only_a_secretariat_draft(): void
    {
        $actor = User::factory()->create();
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'CENTRAL',
            'name' => 'Central Secretariat',
            'office_type' => 'central',
        ]);

        $result = app(FounderLowRiskDomainActionService::class)->execute('secretariat', 'draft_correspondence', [
            'office_id' => $office->id,
            'requested_by' => $actor->id,
            'direction' => 'outgoing',
            'attributes' => [
                'title' => 'Draft reply',
                'subject' => 'Subject',
                'body' => 'Draft body',
                'channel' => 'email',
            ],
            'parties' => [
                [
                    'role' => 'sender',
                    'party_type' => 'external',
                    'display_name' => 'EarthCoop',
                    'email' => 'office@example.test',
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'external',
                    'display_name' => 'Recipient',
                    'email' => 'recipient@example.test',
                ],
            ],
            'reason_code' => 'draft-correspondence-test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('draft_ready', $result['status']);

        $record = SecretariatRecord::query()->findOrFail($result['record_id']);
        $this->assertSame('draft', $record->status);
        $this->assertNull($record->registry_number);
        $this->assertNull($record->registered_at);
        $this->assertSame(0, $record->dispatches()->count());
    }

    public function test_connectivity_reports_draft_as_connected_and_dispatch_as_dependency_blocked(): void
    {
        $snapshot = app(FounderExecutiveConnectivityService::class)->snapshot();
        $secretariat = collect($snapshot['domains'] ?? [])->firstWhere('domain', 'secretariat');

        $this->assertIsArray($secretariat);
        $this->assertContains('draft_correspondence', $secretariat['connected_actions'] ?? []);
        $this->assertContains('register_formal_record', $secretariat['connected_actions'] ?? []);
        $this->assertContains('close_case', $secretariat['connected_actions'] ?? []);
        $this->assertContains('dispatch_formal_record', $secretariat['blocked_actions'] ?? []);
    }
}
