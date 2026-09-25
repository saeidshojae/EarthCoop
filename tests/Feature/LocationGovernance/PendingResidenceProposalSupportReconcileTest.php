<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\PendingResidenceIntent;
use App\Models\User;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PendingResidenceProposalSupportReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_is_dry_run_by_default_and_apply_backfills_only_missing_committed_support(): void
    {
        $schema = LocationFixture::iranSchema();
        $anchor = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();

        $relationship = app(ResidenceService::class)->setInitialPrimaryResidence($user, $anchor, [
            'source' => 'reconcile_test',
        ]);
        $proposal = app(LocationProposalService::class)->propose($user, $anchor, $streetType, [
            'canonical_name' => 'خیابان نیازمند بازسازی حمایت',
        ]);

        $intent = PendingResidenceIntent::query()->create([
            'user_id' => $user->id,
            'anchor_relationship_id' => $relationship->id,
            'location_proposal_id' => $proposal->id,
            'status' => 'pending',
            'selected_at' => now(),
            'metadata' => ['source' => 'legacy_before_committed_support'],
        ]);

        $this->assertSame(0, $proposal->fresh()->evidence()->count());

        $this->assertSame(0, Artisan::call('location:reconcile-residence-support'));
        $dryRunOutput = Artisan::output();
        $this->assertStringContainsString('mode: dry-run', $dryRunOutput);
        $this->assertStringContainsString('missing: 1', $dryRunOutput);
        $this->assertSame(0, $proposal->fresh()->evidence()->count());

        $this->assertSame(0, Artisan::call('location:reconcile-residence-support', ['--apply' => true]));
        $applyOutput = Artisan::output();
        $this->assertStringContainsString('mode: apply', $applyOutput);
        $this->assertStringContainsString('created: 1', $applyOutput);

        $evidence = $proposal->fresh()->evidence()->where('user_id', $user->id)->sole();
        $this->assertSame('residence_commit_reconcile', data_get($evidence->evidence, 'source'));
        $this->assertSame($intent->id, data_get($evidence->evidence, 'pending_residence_intent_id'));

        $this->assertSame(0, Artisan::call('location:reconcile-residence-support', ['--apply' => true]));
        $repeatOutput = Artisan::output();
        $this->assertStringContainsString('created: 0', $repeatOutput);
        $this->assertSame(1, $proposal->fresh()->evidence()->count());
    }
}
