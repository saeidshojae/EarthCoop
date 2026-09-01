<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionResponsibilityAcceptanceEvidenceService;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use App\Services\Elections\ElectionResponsibilityOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ElectionResponsibilityAcceptanceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_cannot_be_accepted_without_exact_explicit_contract_confirmation(): void
    {
        [$election, $candidate] = $this->fixture();
        $offers = app(ElectionResponsibilityOfferService::class);
        $offers->start($election);
        $offer = ElectionResponsibilityOffer::query()->where('election_id', $election->id)->where('status', 'pending')->firstOrFail();

        try {
            $offers->accept($offer, $candidate->id);
            $this->fail('Acceptance without E0 evidence must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('acceptance', strtolower($exception->getMessage()));
        }
        $this->assertSame('pending', $offer->refresh()->status->value);

        $wrongContract = $this->publishContract($candidate, 'manager', 'wrong version');
        try {
            app(ElectionResponsibilityAcceptanceEvidenceService::class)
                ->confirm($offer->refresh(), $candidate, $wrongContract->id);
            $this->fail('Confirmation against a different contract version must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contract_version_id', $exception->errors());
        }

        $confirmedOffer = app(ElectionResponsibilityAcceptanceEvidenceService::class)
            ->confirm($offer->refresh(), $candidate, (int) $offer->contract_version_id);
        $evidence = $confirmedOffer->response_metadata['acceptance_evidence'] ?? null;
        $accepted = $offers->accept($confirmedOffer->refresh(), $candidate->id);

        $this->assertSame('accepted', $accepted->status->value);
        $this->assertIsArray($evidence);
        $this->assertSame($candidate->id, (int) ($evidence['candidate_user_id'] ?? 0));
        $this->assertSame((int) $offer->contract_version_id, (int) ($evidence['contract_version_id'] ?? 0));
        $this->assertNotEmpty($evidence['confirmed_at'] ?? null);
        $this->assertSame(
            hash('sha256', ElectionResponsibilityAcceptanceEvidenceService::CONFIRMATION_TEXT),
            $evidence['confirmation_text_hash'] ?? null
        );
    }

    private function fixture(): array
    {
        $group = Group::create([
            'name' => 'Acceptance evidence group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        GroupSetting::create([
            'level' => 'neighborhood',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 30,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 6,
        ]);
        $candidate = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $candidate->id,
            'role' => 1,
            'status' => 1,
        ]);
        $this->publishContract($candidate, 'manager', 'active manager contract');

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => 'tallying',
        ]);
        ElectionTallyResult::create([
            'election_id' => $election->id,
            'candidate_user_id' => $candidate->id,
            'position' => 'manager',
            'vote_count' => 5,
            'rank' => 1,
            'within_seat_cutoff' => true,
            'cycle_identifier' => 'acceptance-evidence-cycle',
            'stopped_at' => now()->subDay(),
            'vote_snapshot_hash' => str_repeat('a', 64),
            'draw_seed_version' => 'seed-v1',
            'draw_seed' => str_repeat('b', 64),
            'tie_break_version' => 'tie-v1',
            'tie_break_key' => str_repeat('c', 64),
            'tallied_at' => now()->subDay(),
        ]);

        return [$election, $candidate];
    }

    private function publishContract(User $actor, string $position, string $reason): ElectionResponsibilityContractVersion
    {
        $clauses = array_fill_keys(
            ElectionResponsibilityContractVersion::REQUIRED_CLAUSES,
            'متن کامل و روشن قرارداد مسئولیت برای آزمون پذیرش'
        );

        return app(ElectionResponsibilityContractVersionService::class)
            ->publish($position, $clauses, $actor, $reason);
    }
}
