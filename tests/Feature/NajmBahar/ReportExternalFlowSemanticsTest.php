<?php

namespace Tests\Feature\NajmBahar;

use App\Helpers\BaharMoney;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExternalFlowSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dim_activation_is_not_counted_as_income_or_expense_in_report_summary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), [
            'payment_source' => 'dim',
        ])->assertRedirect(route('najm-bahar.dashboard'));

        $response = $this->actingAs($user)->get(route('najm-bahar.reports'));

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary): bool {
            return (int) $summary['totalIn'] === BaharMoney::toGolFromBahar(10_000)
                && (int) $summary['totalOut'] === BaharMoney::toGolFromBahar(12)
                && (int) $summary['net'] === BaharMoney::toGolFromBahar(9_988);
        });
    }

    public function test_direction_filters_only_return_external_flows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ]);
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), [
            'payment_source' => 'dim',
        ]);

        $incoming = $this->actingAs($user)->get(route('najm-bahar.reports', ['type' => 'in']));
        $outgoing = $this->actingAs($user)->get(route('najm-bahar.reports', ['type' => 'out']));

        $incoming->assertOk()->assertViewHas('transactions', fn ($transactions): bool =>
            $transactions->count() === 1
            && ($transactions->first()?->metadata['type'] ?? null) === 'initial_funding'
        );

        $outgoing->assertOk()->assertViewHas('transactions', fn ($transactions): bool =>
            $transactions->count() === 3
            && $transactions->every(fn ($transaction): bool => ($transaction->metadata['type'] ?? null) === 'membership_fee')
        );
    }

    public function test_all_transactions_marks_bucket_activation_as_internal_movement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('najm-bahar.agreement.process'), [
            'agreement_accepted' => '1',
        ]);
        $this->actingAs($user)->post(route('najm-bahar.membership-fee.pay'), [
            'payment_source' => 'dim',
        ]);

        $response = $this->actingAs($user)->get(route('najm-bahar.reports'));

        $response->assertOk();
        $response->assertViewHas('reportAccountIds', fn (array $ids): bool => count($ids) >= 1);
        $response->assertSee('انتقال داخلی');
    }
}
