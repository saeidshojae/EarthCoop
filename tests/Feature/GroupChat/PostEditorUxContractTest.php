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

    public function test_group_post_editor_toolbar_visibility_is_scoped_to_post_modal(): void
    {
        $css = file_get_contents(public_path('Css/group-chat-modals-responsive.css'));

        $this->assertStringContainsString('#postFormBox .cke_top', $css);
        $this->assertStringContainsString('#postFormBox .cke_bottom', $css);
        $this->assertStringContainsString('display: none !important;', $css);
        $this->assertStringContainsString('#postFormBox .cke_contents', $css);
    }
}
