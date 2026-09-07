<?php

namespace Tests\Feature\NajmHoda;

use Tests\TestCase;

class NajmHodaWidgetAvatarContractTest extends TestCase
{
    public function test_widget_uses_the_najm_hoda_avatar_asset_for_its_visible_identity(): void
    {
        $widget = file_get_contents(resource_path('views/components/najm-hoda-widget.blade.php'));

        $this->assertStringContainsString("data-avatar-url=\"{{ asset('images/najm-hoda/avatar.webp') }}\"", $widget);
        $this->assertStringContainsString('class="najm-hoda-toggle-avatar"', $widget);
        $this->assertStringContainsString('class="najm-hoda-header-avatar"', $widget);
        $this->assertStringContainsString('class="najm-hoda-message-avatar-image"', $widget);
        $this->assertStringNotContainsString('<i class="fas fa-robot"></i>', $widget);
    }

    public function test_dynamic_assistant_messages_reuse_the_same_avatar_instead_of_agent_emoji(): void
    {
        $widget = file_get_contents(resource_path('views/components/najm-hoda-widget.blade.php'));

        $this->assertStringContainsString('getAvatarUrl()', $widget);
        $this->assertStringContainsString("const avatarMarkup = role !== 'user'", $widget);
        $this->assertStringContainsString('najm-hoda-message-avatar-image', $widget);
        $this->assertStringContainsString('this.addMessage(data.message, \'assistant\'', $widget);
        $this->assertStringNotContainsString("'assistant', '⚠️'", $widget);
        $this->assertStringNotContainsString("'assistant', '❌'", $widget);
    }

    public function test_najm_hoda_has_a_stable_profile_route_independent_of_user_id(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Profile/ProfileController.php'));

        $this->assertStringContainsString("Route::get('/najm-hoda', [ProfileController::class, 'showNajmHodaProfile'])->name('najm-hoda.profile');", $routes);
        $this->assertStringContainsString('public function showNajmHodaProfile()', $controller);
        $this->assertStringContainsString("config('najm-hoda.group_assistant.bot_email'", $controller);
        $this->assertStringContainsString("return view('profile.najm-hoda'", $controller);
    }

    public function test_generic_member_profile_redirects_the_najm_hoda_system_identity(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Profile/ProfileController.php'));

        $this->assertStringContainsString("return redirect()->route('najm-hoda.profile');", $controller);
        $this->assertStringContainsString("config('najm-hoda.group_assistant.bot_email'", $controller);
    }

    public function test_najm_hoda_profile_explains_identity_services_usage_and_transparency(): void
    {
        $profile = file_get_contents(resource_path('views/profile/najm-hoda.blade.php'));

        $this->assertStringContainsString('هویت رسمی سامانه', $profile);
        $this->assertStringContainsString('چه کارهایی می‌توانم برای شما انجام دهم؟', $profile);
        $this->assertStringContainsString('چطور از نجم هدا استفاده کنم؟', $profile);
        $this->assertStringContainsString('حریم خصوصی و شفافیت', $profile);
        $this->assertStringContainsString('images/najm-hoda/avatar.webp', $profile);
        $this->assertStringContainsString('data-najm-hoda-open', $profile);
        $this->assertStringContainsString('hoda-profile-mobile-polish', $profile);
    }
}
