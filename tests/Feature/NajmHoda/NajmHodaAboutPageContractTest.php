<?php

namespace Tests\Feature\NajmHoda;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NajmHodaAboutPageContractTest extends TestCase
{
    public function test_authenticated_users_have_a_clear_najm_hoda_introduction_route_and_view(): void
    {
        $viewPath = resource_path('views/najm-hoda/about.blade.php');

        $this->assertTrue(Route::has('najm-hoda.about'));
        $this->assertFileExists($viewPath);

        $view = file_get_contents($viewPath);
        $this->assertStringContainsString('نجم هدا', $view);
        $this->assertStringContainsString('همراه', $view);
        $this->assertStringContainsString('حریم خصوصی', $view);
        $this->assertStringContainsString('بدون اجازه', $view);
        $this->assertStringContainsString('najm-hoda-toggle', $view);
    }
}
