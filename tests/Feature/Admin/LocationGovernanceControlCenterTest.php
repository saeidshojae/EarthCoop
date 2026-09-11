<?php

namespace Tests\Feature\Admin;

use App\Enums\LocationGovernance\LocationProposalStatus;
use App\Models\Location;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationGovernanceControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_location_governance_control_center(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/location-governance');

        $response->assertRedirect('/home');
    }

    public function test_admin_can_see_pending_proposals_and_review_context(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$proposal] = $this->makeProposal('مجتمع نیازمند بررسی');

        $response = $this->actingAs($admin)->get('/admin/location-governance');

        $response->assertOk();
        $response->assertSee('مرکز کنترل مکان و حکمرانی');
        $response->assertSee('مجتمع نیازمند بررسی');
        $response->assertSee('پیشنهادهای در انتظار بررسی');
        $response->assertSee('واردسازی');
        $response->assertSee('نگاشت حکمرانی');
    }

    public function test_admin_sidebar_exposes_location_governance_control_center(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertIsString($sidebar);
        $this->assertStringContainsString("route('admin.location-governance.index')", $sidebar);
        $this->assertStringContainsString('مکان و حکمرانی', $sidebar);
        $this->assertStringContainsString("request()->routeIs('admin.location-governance.*')", $sidebar);
    }

    public function test_sensitive_review_actions_are_explicit_admin_writes_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        [$proposal] = $this->makeProposal('مجتمع تایید انسانی');

        $response = $this->actingAs($admin)->postJson("/admin/location-governance/proposals/{$proposal->id}/approve", [
            'reason' => 'مدارک و شواهد توسط مدیر بررسی شد.',
        ]);

        $response->assertOk()->assertJsonPath('status', LocationProposalStatus::Approved->value);
        $proposal->refresh();
        $this->assertSame(LocationProposalStatus::Approved, $proposal->status);
        $this->assertSame($admin->id, $proposal->reviewed_by_user_id);
        $this->assertTrue(collect($proposal->audit_log ?? [])->contains(
            fn (array $entry) => ($entry['to'] ?? null) === LocationProposalStatus::Approved->value
                && (int) ($entry['actor_user_id'] ?? 0) === $admin->id
        ));
    }

    public function test_non_admin_cannot_execute_sensitive_review_action(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        [$proposal] = $this->makeProposal('مجتمع حفاظت‌شده');

        $response = $this->actingAs($user)->postJson("/admin/location-governance/proposals/{$proposal->id}/reject", [
            'reason' => 'نباید قابل اجرا باشد.',
        ]);

        $this->assertTrue(in_array($response->status(), [302, 403], true));
        $this->assertSame(LocationProposalStatus::Pending, $proposal->fresh()->status);
    }

    /** @return array{0: \App\Models\LocationProposal, 1: Location} */
    private function makeProposal(string $name): array
    {
        $schema = LocationFixture::iranSchema();
        $parent = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ])->last();
        $type = $schema->types->firstWhere('key', 'complex');

        $proposal = app(LocationProposalService::class)->propose(
            User::factory()->create(),
            $parent,
            $type,
            ['canonical_name' => $name],
        );

        return [$proposal, $parent];
    }
}
