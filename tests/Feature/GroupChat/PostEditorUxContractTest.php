<?php

namespace Tests\Feature\GroupChat;

use Tests\TestCase;

class PostEditorUxContractTest extends TestCase
{
    public function test_group_post_editor_uses_compact_essential_toolbar(): void
    {
        $runtime = file_get_contents(resource_path('views/groups/partials/ckeditor_runtime.blade.php'));

        $this->assertStringContainsString("height: 180", $runtime);
        $this->assertStringContainsString("['Bold', 'Italic', 'Underline']", $runtime);
        $this->assertStringContainsString("['Link', 'Unlink']", $runtime);
        $this->assertStringContainsString("['BulletedList', 'NumberedList']", $runtime);
        $this->assertStringContainsString("['Undo', 'Redo']", $runtime);
        $this->assertStringContainsString("resize_enabled: false", $runtime);
        $this->assertStringContainsString("elementspath", $runtime);
        $this->assertStringNotContainsString("toolbarGroups:", $runtime);
        $this->assertStringNotContainsString("instance.resize('100%', 400)", $runtime);
    }

    public function test_group_post_editor_chrome_is_scoped_to_post_modal(): void
    {
        $modal = file_get_contents(resource_path('views/groups/modals/post_form.blade.php'));

        $this->assertStringContainsString('#postFormBox .cke_top', $modal);
        $this->assertStringContainsString('#postFormBox .cke_bottom', $modal);
        $this->assertStringContainsString('display: none !important;', $modal);
        $this->assertStringContainsString('#postFormBox .cke_contents', $modal);
        $this->assertStringNotContainsString('.cke_top {', preg_replace('/#postFormBox \.cke_top/', '', $modal) ?? $modal);
    }

    public function test_group_post_editor_warms_library_after_initial_page_interactivity(): void
    {
        $runtime = file_get_contents(resource_path('views/groups/partials/ckeditor_runtime.blade.php'));

        $this->assertStringContainsString('function warmPostEditorLibrary()', $runtime);
        $this->assertStringContainsString('requestIdleCallback', $runtime);
        $this->assertStringContainsString('warmPostEditorLibrary', $runtime);
        $this->assertStringContainsString('initializePostEditor();', $runtime);
    }
}
