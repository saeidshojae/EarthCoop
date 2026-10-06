<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class ProfileTemporalPresentationTest extends TestCase
{
    public function test_profile_views_use_shared_temporal_components(): void
    {
        $member = file_get_contents(base_path('resources/views/profile/profile-member-base.blade.php'));
        $profile = file_get_contents(base_path('resources/views/profile/profile.blade.php'));

        $this->assertStringContainsString('<x-temporal.date :value="$user->birth_date" style="short" />', $member);
        $this->assertStringContainsString('<x-temporal.date :value="$user->created_at" style="short" />', $member);
        $this->assertStringContainsString('@if($user->national_id != null && $user->birth_date)', $member);

        $this->assertStringContainsString('<x-temporal.date-time :value="$request->created_at" style="short" />', $profile);
        $this->assertStringContainsString('<x-temporal.date :value="Auth::user()->birth_date" style="short" />', $profile);
        $this->assertStringContainsString('<x-temporal.date :value="Auth::user()->created_at" style="short" />', $profile);
        $this->assertStringContainsString('@if(Auth::user()->birth_date)', $profile);

        $this->assertStringNotContainsString('verta(', $member);
        $this->assertStringNotContainsString('verta(', $profile);
    }
}
