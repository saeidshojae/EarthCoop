<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Services\Legal\LegalDocumentPublicationService;
use Illuminate\Support\Facades\DB;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use App\Modules\NajmBahar\Services\MembershipEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialMembershipCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_najm_bahar_agreement_creates_exactly_ten_thousand_bahar_as_dim_money(): void
    {
        $this->allowEligibleMembership();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ]);

        $response->assertRedirect(route('najm-bahar.dashboard'));

        $account = Account::where('user_id', $user->id)
            ->where('type', 'user')
            ->firstOrFail();

        $expected = BaharMoney::toGolFromBahar(10_000);

        $this->assertSame(NajmBaharConstitution::initialMembershipGol(), $expected);
        $this->assertSame($expected, (int) $account->balance);
        $this->assertSame(0, (int) $account->balance_active);
        $this->assertSame($expected, (int) $account->balance_faded);

        $initialFunding = Transaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')
            ->firstOrFail();

        $this->assertSame($expected, (int) $initialFunding->amount);
        $this->assertSame('money_created', data_get($initialFunding->metadata, 'monetary_event'));
        $this->assertSame('membership', data_get($initialFunding->metadata, 'issuance_reason'));
        $this->assertSame('membership-issuance-' . $user->id, data_get($initialFunding->metadata, 'idempotency_key'));
        $this->assertSame(0, (int) data_get($initialFunding->metadata, 'active_amount'));
        $this->assertSame($expected, (int) data_get($initialFunding->metadata, 'faded_amount'));

        $ledger = LedgerEntry::where('transaction_id', $initialFunding->id)->firstOrFail();
        $this->assertSame($account->id, (int) $ledger->account_id);
        $this->assertSame($expected, (int) $ledger->amount);
        $this->assertSame('credit', $ledger->entry_type);
        $this->assertSame('faded', data_get($ledger->meta, 'balance_bucket'));
    }

    public function test_reopening_dashboard_does_not_issue_membership_credit_twice(): void
    {
        $this->allowEligibleMembership();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $account = Account::where('user_id', $user->id)
            ->where('type', 'user')
            ->firstOrFail();

        $before = (int) $account->balance;

        $this->actingAs($user)->get(route('najm-bahar.dashboard'))->assertOk();

        $account->refresh();

        $this->assertSame($before, (int) $account->balance);
        $this->assertSame(1, Transaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')
            ->count());
        $this->assertSame(1, LedgerEntry::where('account_id', $account->id)
            ->where('meta->monetary_event', 'money_created')
            ->count());
    }

    public function test_existing_account_can_accept_new_financial_version_without_second_issuance(): void
    {
        $this->allowEligibleMembership();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $account = Account::where('user_id', $user->id)->where('type', 'user')->firstOrFail();
        $balanceBefore = (int) $account->balance;
        $issuancesBefore = Transaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')->count();

        $document = LegalDocument::create([
            'slug' => 'najm-bahar',
            'title' => 'توافقنامه نجم‌بهار',
            'source_type' => 'najm_bahar_agreements',
        ]);
        $version = app(LegalDocumentPublicationService::class)->publish(
            LegalDocumentVersion::create([
                'legal_document_id' => $document->id,
                'version_label' => '1.0',
                'language' => 'fa',
                'status' => 'draft',
            ]),
            'متن نسخه جدید',
            $user->id
        );

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
            'legal_version_id' => $version->id,
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $this->assertDatabaseHas('legal_document_acceptances', [
            'user_id' => $user->id,
            'legal_document_version_id' => $version->id,
            'context' => 'najm_bahar',
        ]);
        $this->assertSame($balanceBefore, (int) $account->fresh()->balance);
        $this->assertSame($issuancesBefore, Transaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')->count());
        $this->assertSame(1, DB::table('legal_document_acceptances')
            ->where('user_id', $user->id)->where('context', 'najm_bahar')->count());
    }

    private function allowEligibleMembership(): void
    {
        $this->mock(MembershipEligibilityService::class, function ($mock) {
            $mock->shouldReceive('isEligibleForInitialMembershipCredit')->andReturnTrue();
        });
    }
}
