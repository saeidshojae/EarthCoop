<?php

namespace Tests\Feature\Communication;

use App\Models\Communication;
use App\Models\CommunicationDeliveryAttempt;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommunicationAdminObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_non_admin_cannot_open_communication_center(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get('/admin/communications')
            ->assertRedirect('/home');
    }

    public function test_admin_dashboard_exposes_delivery_and_rule_health_metrics(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->recipient('queued@example.test', 'queued');
        $this->recipient('sent@example.test', 'sent');
        $this->recipient('retrying@example.test', 'retrying');
        $this->recipient('failed@example.test', 'failed');

        $response = $this->actingAs($admin)->get('/admin/communications');

        $response->assertOk()
            ->assertViewIs('admin.communications.dashboard')
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics['queued'] === 1
                    && $metrics['sent'] === 1
                    && $metrics['retrying'] === 1
                    && $metrics['failed'] === 1
                    && $metrics['active_rules'] >= 1
                    && array_key_exists('due_runs', $metrics)
                    && array_key_exists('upcoming_runs', $metrics);
            })
            ->assertSee('مرکز ارتباطات');
    }

    public function test_delivery_history_filters_by_status_and_email(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->recipient('alpha@example.test', 'sent');
        $this->recipient('beta@example.test', 'failed');

        $this->actingAs($admin)
            ->get('/admin/communications/history?status=sent&email=alpha%40example.test')
            ->assertOk()
            ->assertViewIs('admin.communications.history')
            ->assertSee('alpha@example.test')
            ->assertDontSee('beta@example.test');
    }

    public function test_failure_page_exposes_retry_and_permanent_failure_details(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $retrying = $this->recipient('retry@example.test', 'retrying');
        CommunicationDeliveryAttempt::query()->create([
            'communication_recipient_id' => $retrying->id,
            'attempt_number' => 1,
            'provider' => 'smtp',
            'status' => 'failed',
            'failure_class' => 'transient',
            'failure_code' => 'timeout',
            'failure_message' => 'Temporary timeout',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(2),
        ]);
        CommunicationDeliveryAttempt::query()->create([
            'communication_recipient_id' => $retrying->id,
            'attempt_number' => 2,
            'provider' => 'smtp',
            'status' => 'retrying',
            'failure_class' => 'transient',
            'failure_code' => 'rate_limited',
            'failure_message' => 'Retry scheduled',
            'started_at' => now()->subMinute(),
            'finished_at' => now()->subMinute(),
        ]);

        $failed = $this->recipient('permanent@example.test', 'failed');
        CommunicationDeliveryAttempt::query()->create([
            'communication_recipient_id' => $failed->id,
            'attempt_number' => 1,
            'provider' => 'smtp',
            'status' => 'failed',
            'failure_class' => 'permanent',
            'failure_code' => 'invalid_destination',
            'failure_message' => 'Mailbox rejected',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/communications/failures')
            ->assertOk()
            ->assertViewIs('admin.communications.failures')
            ->assertSee('retry@example.test')
            ->assertSee('permanent@example.test')
            ->assertSee('rate_limited')
            ->assertSee('invalid_destination')
            ->assertSee('2 تلاش');
    }

    private function recipient(string $email, string $status): CommunicationRecipient
    {
        $version = CommunicationTemplateVersion::query()
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->firstOrFail();

        $communication = Communication::query()->create([
            'communication_template_version_id' => $version->id,
            'classification' => 'operational',
            'priority' => 2,
            'status' => $status === 'sent' ? 'sent' : 'processing',
            'deduplication_key' => 'admin-observability:'.$email.':'.$status,
        ]);

        return CommunicationRecipient::query()->create([
            'communication_id' => $communication->id,
            'email' => $email,
            'locale' => 'fa',
            'communication_template_version_id' => $version->id,
            'status' => $status,
            'queued_at' => now()->subMinutes(5),
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ]);
    }
}
