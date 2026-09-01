<?php

namespace Tests\Feature\GroupChat;

use Tests\TestCase;

class PostEditorToolbarVisibilityContractTest extends TestCase
{
    public function test_post_editor_explicitly_restores_ckeditor_chrome_inside_post_modal(): void
    {
        $view = file_get_contents(resource_path('views/groups/modals/post_form.blade.php'));

        $this->assertStringContainsString('#postFormBox .cke_top', $view);
        $this->assertStringContainsString('#postFormBox .cke_bottom', $view);
        $this->assertStringContainsString('display: block !important', $view);
    }
}
