<?php

namespace Tests\Feature\Invitation;

use App\Http\Controllers\Admin\ReputationController;
use App\Http\Controllers\Profile\MemberInvitationController;
use App\Models\ReputationRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationLaunchContractTest extends TestCase
{
    #[Test]
    public function launch_defaults_are_invite_only_with_ten_successful_slots_and_one_hundred_points(): void
    {
        $seeder = file_get_contents(database_path('seeders/SettingSeeder.php'));
        $reputation = require config_path('reputation.php');

        $this->assertStringContainsString("'invation_status' => true", $seeder);
        $this->assertStringContainsString("'count_invation' => 10", $seeder);
        $this->assertSame(100, (int) $reputation['weights']['invite_member']);
    }

    #[Test]
    public function invite_reward_is_finalized_by_registration_completion_not_najm_bahar_agreement(): void
    {
        $profileCompletion = file_get_contents(app_path('Services/ProfileCompletionService.php'));
        $najmBahar = file_get_contents(app_path('Http/Controllers/NajmBaharController.php'));

        $this->assertStringContainsString('InvitationLifecycleService', $profileCompletion);
        $this->assertStringContainsString('completeSuccessfulInvitation', $profileCompletion);
        $this->assertStringNotContainsString('processReferralParticipation($user)', $najmBahar);
    }

    #[Test]
    public function member_quota_is_enforced_by_the_canonical_invitation_lifecycle(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Profile/MemberInvitationController.php'));
        $routes = file_get_contents(base_path('routes/member-invitations.php'));

        $this->assertStringContainsString('InvitationLifecycleService', $controller);
        $this->assertStringContainsString('canIssueMemberInvitation', $controller);
        $this->assertStringContainsString('issueMemberInvitation', $controller);
        $this->assertStringContainsString("/profile/invation-code-generate", $routes);
        $this->assertStringContainsString("profile.member-invitations.store", $routes);
    }

    #[Test]
    public function member_invitation_issuance_rechecks_quota_under_a_referrer_lock(): void
    {
        $service = file_get_contents(app_path('Services/InvitationLifecycleService.php'));

        $this->assertStringContainsString('public function issueMemberInvitation', $service);
        $this->assertStringContainsString('DB::transaction', $service);
        $this->assertStringContainsString('User::whereKey($referrer->id)', $service);
        $this->assertStringContainsString('lockForUpdate()', $service);
        $this->assertStringContainsString('$this->occupiedSlots($lockedReferrer) >= $this->quota()', $service);
    }

    #[Test]
    public function canonical_member_invitation_route_is_authenticated_and_post_only(): void
    {
        $route = app('router')->getRoutes()->getByName('profile.member-invitations.store');

        $this->assertNotNull($route);
        $this->assertSame(MemberInvitationController::class, $route->getActionName());
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains(\App\Http\Middleware\Authenticate::class, $route->gatherMiddleware());

        $legacyGet = app('router')->getRoutes()->getByName('profile.generate-code');
        $this->assertNotNull($legacyGet);
        $this->assertSame(['GET', 'HEAD'], $legacyGet->methods());
        $this->assertNotSame('App\\Http\\Controllers\\Profile\\ProfileController@generateInvationCode', $legacyGet->getActionName());

        $view = file_get_contents(resource_path('views/profile/member-invitations.blade.php'));
        $this->assertStringContainsString('method="POST"', $view);
        $this->assertStringContainsString('@csrf', $view);
        $this->assertStringNotContainsString('href="{{ route(\'profile.generate-code\') }}"', $view);
    }

    #[Test]
    public function invitation_share_link_uses_the_actual_registration_form_route(): void
    {
        $this->assertNotNull(app('router')->getRoutes()->getByName('register.form'));
        $this->assertNull(app('router')->getRoutes()->getByName('register'));

        $view = file_get_contents(resource_path('views/profile/member-invitations.blade.php'));
        $this->assertStringContainsString("route('register.form')", $view);
        $this->assertStringNotContainsString("route('register')", $view);
    }

    #[Test]
    public function registration_claims_invitation_atomically(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Auth/Register/StartController.php'));

        $this->assertStringContainsString('DB::transaction', $controller);
        $this->assertStringContainsString('lockForUpdate()', $controller);
    }

    #[Test]
    public function invite_member_remains_an_admin_managed_convertible_rule_with_new_default(): void
    {
        app(ReputationController::class)->index();

        $rule = ReputationRule::where('key', 'invite_member')->firstOrFail();

        $this->assertSame(100, (int) $rule->weight);
        $this->assertSame('participation', $rule->dimension);
        $this->assertTrue((bool) $rule->convertible);
        $this->assertSame('once_per_context', $rule->repeat_policy);
    }
}