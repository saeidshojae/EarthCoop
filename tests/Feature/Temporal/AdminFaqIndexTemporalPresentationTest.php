<?php

namespace Tests\Feature\Temporal;

use Tests\TestCase;

class AdminFaqIndexTemporalPresentationTest extends TestCase
{
    public function test_admin_faq_index_uses_shared_temporal_presentation_for_php_and_js(): void
    {
        $view = file_get_contents(base_path('resources/views/admin/faq/index.blade.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Admin/FaqQuestionController.php'));

        $this->assertSame(1, substr_count($view, '<x-temporal.date :value="$createdAt" />'));
        $this->assertSame(1, substr_count($view, '<x-temporal.time :value="$createdAt" />'));
        $this->assertSame(1, substr_count($view, '<x-temporal.date :value="$answeredAt" />'));
        $this->assertStringContainsString('question.notified_at_display', $view);

        $this->assertStringNotContainsString('Morilog\\Jalali', $view);
        $this->assertStringNotContainsString('Jalalian::', $view);
        $this->assertStringNotContainsString("toLocaleDateString('fa-IR'", $view);
        $this->assertStringNotContainsString('toLocaleDateString("fa-IR"', $view);

        $this->assertStringContainsString("'notified_at_display'", $controller);
        $this->assertStringContainsString('$temporal->date(', $controller);
        $this->assertStringContainsString("'short'", $controller);
    }
}
