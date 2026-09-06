<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ReputationSurfacePolishContractTest extends TestCase
{
    public function test_public_profile_reputation_is_integrated_into_profile_identity_flow_instead_of_full_width_hero(): void
    {
        $view = file_get_contents(resource_path('views/profile/profile-member.blade.php'));

        $this->assertStringContainsString('reputation-public-card--embedded', $view);
        $this->assertStringContainsString('اعتبار و مشارکت', $view);
        $this->assertStringContainsString('aria-label="اعتبار و مشارکت کاربر"', $view);
        $this->assertStringNotContainsString('reputation-public-card__header', $view);
        $this->assertStringContainsString('@media (max-width: 640px)', $view);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $view);
    }

    public function test_private_points_surface_separates_social_reputation_from_convertible_economy(): void
    {
        $view = file_get_contents(resource_path('views/history/index.blade.php'));

        $this->assertStringContainsString('reputation-overview', $view);
        $this->assertStringContainsString('reputation-conversion-card', $view);
        $this->assertStringContainsString('امتیاز مشارکت قابل تبدیل', $view);
        $this->assertStringContainsString('در حال حاضر امتیاز مشارکت قابل تبدیل ندارید', $view);
        $this->assertStringContainsString('@disabled($remainingConvertiblePoints < 1)', $view);
        $this->assertStringContainsString('@media (max-width: 640px)', $view);
    }

    public function test_member_facing_reputation_history_uses_persian_human_readable_labels(): void
    {
        $view = file_get_contents(resource_path('views/history/index.blade.php'));

        $this->assertStringContainsString("'profile_completed' => 'تکمیل پروفایل'", $view);
        $this->assertStringContainsString("'email_verified' => 'تأیید ایمیل'", $view);
        $this->assertStringContainsString("'profile' => 'پروفایل کاربری'", $view);
        $this->assertStringContainsString("'auth' => 'احراز حساب'", $view);
        $this->assertStringNotContainsString('<th>عمل</th>', $view);
        $this->assertStringNotContainsString('<th>ماخذ</th>', $view);
    }
}
