<?php

namespace Tests\Feature\NajmHoda;

use App\Models\EmailTemplate;
use App\Modules\Blog\Models\Post;
use App\Observers\NajmHoda\FounderManagedContentObserver;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class FounderManagedContentObserverTest extends TestCase
{
    public function test_email_template_event_excludes_body(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $template = new EmailTemplate([
            'name' => 'Welcome',
            'subject' => 'Hello',
            'body' => 'private body',
            'category' => 'onboarding',
            'is_active' => true,
        ]);
        $template->id = 15;

        (new FounderManagedContentObserver())->created($template);

        $events = $bus->recent('najm_hoda.input.email.created', 1);
        $this->assertNotEmpty($events);
        $payload = data_get($events[0], 'payload', []);
        $this->assertSame(15, (int) ($payload['entity_id'] ?? 0));
        $this->assertSame('email_template', $payload['entity_type'] ?? null);
        $this->assertArrayNotHasKey('body', $payload);
    }

    public function test_blog_post_event_captures_editorial_state_without_content(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $post = new Post([
            'title' => 'EarthCoop update',
            'content' => 'long article body',
            'status' => 'draft',
            'user_id' => 9,
            'is_featured' => false,
        ]);
        $post->id = 21;

        (new FounderManagedContentObserver())->created($post);

        $events = $bus->recent('najm_hoda.input.blog.created', 1);
        $this->assertNotEmpty($events);
        $payload = data_get($events[0], 'payload', []);
        $this->assertSame('draft', $payload['status'] ?? null);
        $this->assertSame(9, (int) ($payload['author_id'] ?? 0));
        $this->assertArrayNotHasKey('content', $payload);
    }
}
