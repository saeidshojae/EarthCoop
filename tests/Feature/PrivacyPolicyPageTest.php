<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyPageTest extends TestCase
{
    public function test_privacy_policy_is_public_indexable_and_canonical(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertViewIs('privacy');
        $response->assertSee('سیاست حریم خصوصی ارث‌کوپ');
        $response->assertSee('index,follow', false);
        $response->assertSee('https://earthcoop.ir/privacy', false);
    }

    public function test_privacy_policy_explains_google_sign_in_and_user_rights(): void
    {
        $response = $this->get('/privacy');

        $response->assertSee('ورود با حساب گوگل');
        $response->assertSee('رمز عبور حساب گوگل');
        $response->assertSee('درخواست حذف اطلاعات');
        $response->assertSee('contact@earthcoop.ir');
    }

    public function test_public_footer_links_to_the_privacy_policy(): void
    {
        $response = $this->get('/privacy');

        $response->assertSee(route('privacy'), false);
        $response->assertSee('حریم خصوصی');
    }
}
