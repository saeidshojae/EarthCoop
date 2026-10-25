<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class PrivacyPolicyLocalizationTest extends TestCase
{
    /** @dataProvider privacyPolicyProvider */
    public function test_privacy_policy_content_and_metadata_follow_the_selected_locale(
        string $locale,
        string $expectedTitle,
        string $expectedSection,
        string $expectedTermsLabel,
    ): void {
        $html = $this->withSession(['locale' => $locale])->get('/privacy')->assertOk()->getContent();

        $this->assertStringContainsString($expectedTitle, $html);
        $this->assertStringContainsString('<title>' . $expectedTitle . '</title>', $html);
        $this->assertStringContainsString($expectedSection, $html);
        $this->assertStringContainsString($expectedTermsLabel, $html);
        $this->assertStringContainsString('content="index,follow"', $html);
        $this->assertStringContainsString('href="https://earthcoop.ir/privacy"', $html);
        $this->assertStringContainsString('href="mailto:contact@earthcoop.ir"', $html);
    }

    public static function privacyPolicyProvider(): array
    {
        return [
            'Persian' => ['fa', 'سیاست حریم خصوصی ارث‌کوپ', 'ورود با حساب گوگل', 'شرایط استفاده'],
            'English' => ['en', 'EarthCoop Privacy Policy', 'Sign-in with Google', 'Terms of Use'],
            'Arabic' => ['ar', 'سياسة الخصوصية في EarthCoop', 'تسجيل الدخول عبر Google', 'شروط الاستخدام'],
        ];
    }
}
