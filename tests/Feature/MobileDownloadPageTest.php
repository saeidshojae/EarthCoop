<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileDownloadPageTest extends TestCase
{
    public function test_mobile_download_page_is_public_and_exposes_android_uat_apk(): void
    {
        $response = $this->get('/app');

        $response
            ->assertOk()
            ->assertSee('اپلیکیشن EarthCoop')
            ->assertSee('نسخه آزمایشی', false)
            ->assertSee('/downloads/mobile/earthcoop-android-uat.apk', false);
    }
}
