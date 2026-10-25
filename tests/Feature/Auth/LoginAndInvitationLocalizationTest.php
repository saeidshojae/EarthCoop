<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginAndInvitationLocalizationTest extends TestCase
{
    public function test_login_page_is_localized_in_english_and_arabic(): void
    {
        $cases = [
            'en' => ['Welcome', 'Sign in to EarthCoop', 'Email address', 'Sign in with Google'],
            'ar' => ['مرحبًا بك', 'سجّل الدخول إلى EarthCoop', 'عنوان البريد الإلكتروني', 'تسجيل الدخول باستخدام Google'],
        ];

        foreach ($cases as $locale => $expectedTexts) {
            $response = $this->withSession(['locale' => $locale])->get(route('login'));

            $response->assertOk();
            foreach ($expectedTexts as $text) {
                $response->assertSee($text);
            }
            $response->assertDontSee('به زمین نو وارد شوید');
        }
    }

    public function test_invitation_request_page_is_localized_in_english_and_arabic(): void
    {
        $cases = [
            'en' => ['Request an invitation code', 'Join the EarthCoop community', 'How did you hear about us?', 'Submit request and get a code'],
            'ar' => ['طلب رمز دعوة', 'انضم إلى مجتمع EarthCoop', 'كيف سمعت عنا؟', 'إرسال الطلب والحصول على رمز'],
        ];

        foreach ($cases as $locale => $expectedTexts) {
            $response = $this->withSession(['locale' => $locale])->get(route('invite'));

            $response->assertOk();
            foreach ($expectedTexts as $text) {
                $response->assertSee($text);
            }
            $response->assertDontSee('به جامعه زمین نو بپیوندید');
        }
    }
}
