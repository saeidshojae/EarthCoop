<?php

namespace Tests\Feature\GroupChat;

use Tests\TestCase;

class PostEditorLazyLoadContractTest extends TestCase
{
    public function test_post_editor_is_loaded_on_demand_when_the_post_modal_opens(): void
    {
        $chat = file_get_contents(resource_path('views/groups/chat.blade.php'));
        $runtime = file_get_contents(resource_path('views/groups/partials/ckeditor_runtime.blade.php'));
        $composer = file_get_contents(resource_path('js/group-chat/composer.js'));

        $this->assertStringNotContainsString("<script src=\"{{ asset('vendor/ckeditor/ckeditor.js') }}\"></script>", $chat);
        $this->assertStringContainsString("asset('vendor/ckeditor/ckeditor.js')", $runtime);
        $this->assertStringContainsString('loadPostEditor', $runtime);
        $this->assertStringContainsString("'group-chat:post-modal-opened'", $runtime);
        $this->assertStringContainsString("new CustomEvent('group-chat:post-modal-opened')", $composer);
        $this->assertStringContainsString("ckeditor.replace('post_editor'", $runtime);
    }
}
