<?php

namespace Tests\Feature\Invitation;

use App\Models\ReputationRule;
use App\Models\Setting;
use App\Models\User;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Services\InvitationLifecycleService;
use App\Services\MembershipFeeStatusService;
use App\Services\MembershipParticipationEligibilityService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvitationParticipationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_member_cannot_issue_invitation_until_current_membership_fee_is_paid(): void
    {
        $this->configureInvitationPolicy();
        $user = $this->completeMember();
        $service = app(InvitationLifecycleService::class);

        $this->assertSame(1, (int) Setting::singleton()->id);
        $this->assertSame(10, $service->quota());
        $this->assertFalse($service->canIssueMemberInvitation($user));

        $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Invitation gate');
        app(MonetaryService::class)->issueMembershipCredit($account, $user->id);
        $this->assertSame(10, $service->quota());
        $this->assertFalse($service->canIssueMemberInvitation($user->fresh()));

        $connection = DB::connection();
        $beforeRequest = [
            'connection_name' => $connection->getName(),
            'database' => $connection->getDatabaseName(),
            'pdo_id' => spl_object_id($connection->getPdo()),
            'transaction_level' => $connection->transactionLevel(),
            'setting_id' => Setting::query()->find(1)?->id,
            'quota' => Setting::query()->find(1)?->count_invation,
        ];
        $settingWrites = [];
        DB::listen(function (QueryExecuted $query) use (&$settingWrites): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'setting') && (
                str_contains($sql, 'update')
                || str_contains($sql, 'insert')
                || str_contains($sql, 'delete')
                || str_contains($sql, 'truncate')
            )) {
                $settingWrites[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'connection_name' => $query->connectionName,
                    'database' => $query->connection->getDatabaseName(),
                    'pdo_id' => spl_object_id($query->connection->getPdo()),
                    'transaction_level' => $query->connection->transactionLevel(),
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('najm-bahar.membership-fee.pay'), ['payment_source' => 'dim'])
            ->assertRedirect(route('najm-bahar.dashboard'));

        $afterConnection = DB::connection();
        $afterRequest = [
            'connection_name' => $afterConnection->getName(),
            'database' => $afterConnection->getDatabaseName(),
            'pdo_id' => spl_object_id($afterConnection->getPdo()),
            'transaction_level' => $afterConnection->transactionLevel(),
            'setting_id' => Setting::query()->find(1)?->id,
            'quota' => Setting::query()->find(1)?->count_invation,
        ];

        $freshUser = $user->fresh();
        $this->assertSame(
            10,
            $service->quota(),
            'Membership fee payment mutated invitation settings. Context: ' . json_encode([
                'before' => $beforeRequest,
                'writes' => $settingWrites,
                'after' => $afterRequest,
            ], JSON_UNESCAPED_UNICODE)
        );
        $this->assertTrue(app(MembershipFeeStatusService::class)->hasPaidCurrentMembershipFee($freshUser));
        $this->assertTrue(app(MembershipParticipationEligibilityService::class)->isEligible($freshUser));
        $this->assertTrue($service->isEligibleMember($freshUser));
        $this->assertTrue($service->canIssueMemberInvitation($freshUser));
    }

    public function test_invitation_page_contract_uses_dynamic_policy_values_and_membership_ctas(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Profile/MemberInvitationController.php'));
        $routes = file_get_contents(base_path('routes/member-invitations.php'));
        $view = file_get_contents(resource_path('views/profile/member-invitations.blade.php'));

        $this->assertStringContainsString('function index', $controller);
        $this->assertStringContainsString('expiryHours()', $controller);
        $this->assertStringContainsString('remainingSlots', $controller);
        $this->assertStringContainsString("ReputationRule::where('key', 'invite_member')", $controller);
        $this->assertStringContainsString("->get('/my-invation-code'", $routes);
        $this->assertStringContainsString("name('my-invation-code')", $routes);
        $this->assertStringContainsString("route('najm-bahar.agreement')", $view);
        $this->assertStringContainsString("route('najm-bahar.dashboard')", $view);
        $this->assertStringContainsString('{{ $quota }}', $view);
        $this->assertStringContainsString('{{ $expiryHours }}', $view);
        $this->assertStringContainsString('{{ $rewardPoints }}', $view);
        $this->assertStringNotContainsString('<strong>۱۰ بهار</strong>', $view);
    }

    private function configureInvitationPolicy(): void
    {
        $setting = Setting::singleton();
        $setting->fill([
            'invation_status' => true,
            'count_invation' => 10,
            'expire_invation_time' => 72,
        ]);
        $setting->save();

        ReputationRule::query()->updateOrCreate(['key' => 'invite_member'], [
            'label' => 'Invite member', 'weight' => 100, 'active' => true,
            'daily_cap' => null, 'dimension' => 'participation',
            'convertible' => true, 'repeat_policy' => 'once_per_context',
        ]);
    }

    private function completeMember(): User
    {
        $user = User::factory()->create([
            'gender' => 'male',
            'national_id' => 'I' . fake()->unique()->numerify('#########'),
            'phone' => fake()->unique()->numerify('09#########'),
        ]);
        $experienceFieldId = DB::table('experience_fields')->insertGetId([
            'name' => 'Invite Gate ' . $user->id, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_experience_field')->insert([
            'user_id' => $user->id, 'experience_field_id' => $experienceFieldId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('addresses')->insert([
            'user_id' => $user->id, 'continent_id' => 1, 'country_id' => 1,
            'province_id' => 1, 'county_id' => 1, 'section_id' => 1,
            'neighborhood_id' => 1, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user->fresh();
    }
}
