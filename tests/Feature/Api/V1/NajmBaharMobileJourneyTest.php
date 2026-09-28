<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\UserPointTransaction;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountInvariantService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Services\SubAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NajmBaharMobileJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_bearer_client_completes_launch_scope_financial_journey_without_web_forms(): void
    {
        [$user, $token, $deviceId] = $this->nativeSession();

        $accounts = app(AccountService::class);
        $monetary = app(MonetaryService::class);
        $subAccounts = app(SubAccountService::class);
        $reservations = app(ActiveBaharReservationService::class);

        $main = $accounts->createMainAccountForUser((int) $user->id, 'M3 mobile member');
        $monetary->issueMembershipCredit($main, (int) $user->id);
        $monetary->activateDim(
            $main,
            1_000,
            'M3 journey transfer fixture',
            ['type' => 'm3_journey_transfer_fixture'],
            'm3-journey-preload-'.$user->id,
            false,
        );
        $main->refresh();

        $destination = $subAccounts->createSubAccount((int) $main->id, 'Mobile destination');
        $destinationMirror = $accounts->ensureSubAccountAccount($destination);
        $scheduledDestination = $subAccounts->createSubAccount((int) $main->id, 'Scheduled destination');
        $accounts->ensureSubAccountAccount($scheduledDestination);

        $this->policy();
        UserPointTransaction::create([
            'user_id' => (int) $user->id,
            'delta' => 250,
            'balance_after' => 250,
            'action' => 'm3_mobile_journey_award',
            'dimension' => 'participation',
            'convertible' => true,
            'source' => 'm3_mobile_journey',
        ]);

        $accountResponse = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/account')
            ->assertOk()
            ->assertJsonPath('data.id', (int) $main->id)
            ->assertJsonPath('data.balance.local.active_gol', 1_000);
        $this->assertIsInt($accountResponse->json('data.balance.local.total_gol'));

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?page[limit]=20')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $reservations->reserve(
            (string) $main->account_number,
            800,
            'm3-journey-reservation-'.$user->id,
            'm3_mobile_journey',
            (int) $user->id,
        );

        $blockedTransfer = [
            'source_account_id' => (int) $main->id,
            'destination_account_number' => (string) $destinationMirror->account_number,
            'amount_gol' => 300,
            'balance_bucket' => 'active',
            'description' => 'Must respect reserved Active',
        ];

        $this->bearer($token, $deviceId)
            ->withHeader('Idempotency-Key', 'm3-journey-reserved-transfer-0001')
            ->postJson('/api/v1/najm-bahar/transfers', $blockedTransfer)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'insufficient_available_funds');

        $this->assertSame(1_000, (int) $main->fresh()->balance_active);
        $this->assertSame(0, (int) $destination->fresh()->balance_active);

        $reservations->release(
            'm3-journey-reservation-'.$user->id,
            'm3-journey-reservation-release-'.$user->id,
        );

        $transferPayload = $blockedTransfer;
        $transferPayload['amount_gol'] = 250;
        $transferPayload['description'] = 'Native M3 transfer';
        $transferHeaders = ['Idempotency-Key' => 'm3-journey-transfer-0001'];

        $firstTransfer = $this->bearer($token, $deviceId)
            ->withHeaders($transferHeaders)
            ->postJson('/api/v1/najm-bahar/transfers', $transferPayload)
            ->assertCreated()
            ->assertJsonPath('data.transaction.amount_gol', 250)
            ->assertJsonPath('data.transaction.balance_bucket', 'active')
            ->assertJsonPath('data.source_balance.local.active_gol', 750);
        $transferId = (int) $firstTransfer->json('data.transaction.id');
        $this->assertIsInt($firstTransfer->json('data.transaction.amount_gol'));

        $transactionCountAfterTransfer = NajmTransaction::query()->count();
        $ledgerCountAfterTransfer = LedgerEntry::query()->count();

        $this->bearer($token, $deviceId)
            ->withHeaders($transferHeaders)
            ->postJson('/api/v1/najm-bahar/transfers', $transferPayload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.transaction.id', $transferId);

        $this->assertSame($transactionCountAfterTransfer, NajmTransaction::query()->count());
        $this->assertSame($ledgerCountAfterTransfer, LedgerEntry::query()->count());
        $this->assertSame(750, (int) $main->fresh()->balance_active);
        $this->assertSame(250, (int) $destination->fresh()->balance_active);

        $history = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/transactions?page[limit]=20')
            ->assertOk();
        $historyTransfer = collect($history->json('data'))->firstWhere('id', $transferId);
        $this->assertNotNull($historyTransfer);
        $this->assertSame(250, $historyTransfer['amount_gol']);
        $this->assertIsInt($historyTransfer['amount_gol']);

        $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/activation/eligibility')
            ->assertOk()
            ->assertJsonPath('data.source', 'participation')
            ->assertJsonPath('data.max_activation_gol', 2);

        $activationHeaders = ['Idempotency-Key' => 'm3-journey-activation-0001'];
        $activationPayload = ['source' => 'participation', 'points' => 250];
        $firstActivation = $this->bearer($token, $deviceId)
            ->withHeaders($activationHeaders)
            ->postJson('/api/v1/najm-bahar/activation', $activationPayload)
            ->assertCreated()
            ->assertJsonPath('data.consumed_points', 200)
            ->assertJsonPath('data.activated_gol', 2)
            ->assertJsonPath('data.balance.local.active_gol', 752);
        $this->assertIsInt($firstActivation->json('data.activated_gol'));

        $conversionCount = DB::table('user_point_conversions')->where('user_id', $user->id)->count();
        $transactionCountAfterActivation = NajmTransaction::query()->count();
        $ledgerCountAfterActivation = LedgerEntry::query()->count();

        $this->bearer($token, $deviceId)
            ->withHeaders($activationHeaders)
            ->postJson('/api/v1/najm-bahar/activation', $activationPayload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.transaction.id', $firstActivation->json('data.transaction.id'));

        $this->assertSame($conversionCount, DB::table('user_point_conversions')->where('user_id', $user->id)->count());
        $this->assertSame($transactionCountAfterActivation, NajmTransaction::query()->count());
        $this->assertSame($ledgerCountAfterActivation, LedgerEntry::query()->count());

        $membershipInfo = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/membership-fee')
            ->assertOk()
            ->assertJsonPath('data.has_paid', false)
            ->assertJsonPath('data.fee_gol', 1_200)
            ->assertJsonPath('data.default_payment_source', 'dim');
        $this->assertIsInt($membershipInfo->json('data.fee_gol'));

        $membershipHeaders = ['Idempotency-Key' => 'm3-journey-membership-0001'];
        $membershipPayload = ['payment_source' => 'dim'];
        $firstMembership = $this->bearer($token, $deviceId)
            ->withHeaders($membershipHeaders)
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', $membershipPayload)
            ->assertCreated()
            ->assertJsonPath('data.has_paid', true)
            ->assertJsonPath('data.fee_gol', 1_200)
            ->assertJsonPath('data.payment_source', 'dim');
        $this->assertIsInt($firstMembership->json('data.fee_gol'));

        $transactionCountAfterMembership = NajmTransaction::query()->count();
        $ledgerCountAfterMembership = LedgerEntry::query()->count();
        $membershipTransactionCount = NajmTransaction::query()
            ->where('metadata->type', 'membership_fee')
            ->count();

        $this->bearer($token, $deviceId)
            ->withHeaders($membershipHeaders)
            ->postJson('/api/v1/najm-bahar/membership-fee/pay', $membershipPayload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.payment_year', $firstMembership->json('data.payment_year'));

        $this->assertSame($transactionCountAfterMembership, NajmTransaction::query()->count());
        $this->assertSame($ledgerCountAfterMembership, LedgerEntry::query()->count());
        $this->assertSame($membershipTransactionCount, NajmTransaction::query()->where('metadata->type', 'membership_fee')->count());
        $this->assertSame(3, $membershipTransactionCount);

        $scheduled = $this->scheduledTransfer(
            (int) $destination->id,
            (int) $scheduledDestination->id,
            125,
            (int) $user->id,
        );

        $scheduledResponse = $this->bearer($token, $deviceId)
            ->getJson('/api/v1/najm-bahar/scheduled-operations')
            ->assertOk()
            ->assertJsonPath('data.0.id', (int) $scheduled->id)
            ->assertJsonPath('data.0.amount_gol', 125)
            ->assertJsonPath('data.0.balance_bucket', 'active')
            ->assertJsonMissingPath('data.0.payload')
            ->assertJsonMissingPath('data.0.metadata');
        $this->assertIsInt($scheduledResponse->json('data.0.amount_gol'));

        $main->refresh();
        $audit = app(AccountInvariantService::class)->audit($main);
        $this->assertTrue((bool) $audit['is_clean'], json_encode($audit, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->assertSame([], $audit['mirror_drift']);
        $this->assertSame((int) $main->balance_active, $reservations->availableActive($main));
    }

    private function policy(): MonetaryPolicyVersion
    {
        return MonetaryPolicyVersion::create([
            'version' => 9901,
            'status' => 'active',
            'effective_from' => now()->subMinute(),
            'approved_at' => now(),
            'reason' => 'M3 mobile journey',
            'parameters' => [
                'reputation_conversion_enabled' => true,
                'reputation_to_gol_ratio' => 100,
                'membership_fee_gol' => 1_200,
                'membership_operations_gol' => 600,
                'membership_insurance_gol' => 300,
                'membership_burn_gol' => 300,
            ],
        ]);
    }

    private function scheduledTransfer(
        int $fromSubAccountId,
        int $toSubAccountId,
        int $amountGol,
        int $actorUserId,
    ): ScheduledTransaction {
        $executeAt = now()->addDay();
        $placeholder = NajmTransaction::create([
            'from_account_id' => null,
            'to_account_id' => null,
            'amount' => $amountGol,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => $executeAt,
            'description' => 'M3 mobile scheduled evidence',
            'metadata' => [
                'transfer_type' => 'subaccount',
                'from_sub_account_id' => $fromSubAccountId,
                'to_sub_account_id' => $toSubAccountId,
                'money_state' => 'active',
                'actor_user_id' => $actorUserId,
            ],
        ]);

        return ScheduledTransaction::create([
            'transaction_id' => (int) $placeholder->id,
            'execute_at' => $executeAt,
            'status' => 'scheduled',
            'attempts' => 0,
            'payload' => [
                'type' => 'subaccount_transfer',
                'from_sub_account_id' => $fromSubAccountId,
                'to_sub_account_id' => $toSubAccountId,
                'amount' => $amountGol,
                'money_state' => 'active',
                'description' => 'M3 mobile scheduled evidence',
                'metadata' => [
                    'actor_user_id' => $actorUserId,
                ],
            ],
        ]);
    }

    private function nativeSession(): array
    {
        $password = 'secret-password';
        $user = User::factory()->create([
            'email' => 'bahar-journey-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => Hash::make($password),
            'is_system' => false,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/session', [
            'email' => $user->email,
            'password' => $password,
            'platform' => 'android',
            'app_version' => '1.0.0',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'push_capable' => false,
        ])->assertCreated();

        return [$user, (string) $login->json('data.token'), (string) $login->json('data.device.id')];
    }

    private function bearer(string $token, string $deviceId): self
    {
        Auth::forgetGuards();

        return $this->withToken($token)->withHeader('X-Device-ID', $deviceId);
    }
}
