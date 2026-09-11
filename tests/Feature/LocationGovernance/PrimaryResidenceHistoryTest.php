<?php

namespace Tests\Feature\LocationGovernance;

use App\Exceptions\ResidenceTransferLimitExceeded;
use App\Models\User;
use App\Services\LocationGovernance\ResidenceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class PrimaryResidenceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_closes_previous_primary_residence_and_preserves_history(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 12:00:00');
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $schema = LocationFixture::iranSchema();
        $from = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $to = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();

        $service = app(ResidenceService::class);
        $initial = $service->setInitialPrimaryResidence($user, $from, ['source' => 'registration']);
        $next = $service->transferPrimaryResidence($user, $to, $actor, 'moved home');

        $this->assertNotSame($initial->id, $next->id);
        $this->assertNotNull($initial->fresh()->ended_at);
        $this->assertSame('primary_residence', $next->relationship_type);
        $this->assertNull($next->ended_at);
        $this->assertSame($actor->id, $next->changed_by_user_id);
        $this->assertSame('moved home', $next->change_reason);
        $this->assertTrue((bool) $next->explicit_transfer);
        $this->assertCount(2, $user->fresh()->locationRelationships()->where('relationship_type', 'primary_residence')->get());

        CarbonImmutable::setTestNow();
    }

    public function test_third_explicit_transfer_inside_rolling_year_is_blocked_but_audited_override_is_allowed(): void
    {
        CarbonImmutable::setTestNow('2026-09-10 12:00:00');
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $schema = LocationFixture::iranSchema();
        $locations = collect(range(1, 5))->map(fn () => LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last());
        $service = app(ResidenceService::class);

        $service->setInitialPrimaryResidence($user, $locations[0], ['source' => 'registration']);
        $service->transferPrimaryResidence($user, $locations[1], $actor, 'move one');
        CarbonImmutable::setTestNow('2026-10-10 12:00:00');
        $service->transferPrimaryResidence($user, $locations[2], $actor, 'move two');

        CarbonImmutable::setTestNow('2026-11-10 12:00:00');
        try {
            $service->transferPrimaryResidence($user, $locations[3], $actor, 'move three');
            $this->fail('Expected transfer limit exception.');
        } catch (ResidenceTransferLimitExceeded) {
            $this->assertTrue(true);
        }

        $override = $service->transferPrimaryResidence($user, $locations[4], $actor, 'verified exceptional relocation', true);

        $this->assertTrue((bool) $override->transfer_override);
        $this->assertSame($actor->id, $override->changed_by_user_id);
        $this->assertSame('verified exceptional relocation', $override->change_reason);

        CarbonImmutable::setTestNow();
    }
}
