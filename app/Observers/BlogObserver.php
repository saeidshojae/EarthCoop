<?php

namespace App\Observers;

use App\Modules\Blog\Models\Post as BlogPost;
use Illuminate\Support\Facades\Cache;

class BlogObserver
{
    /**
     * Handle the Blog "created" event.
     */
    public function created(BlogPost $blog): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Blog "updated" event.
     */
    public function updated(BlogPost $blog): void
    {
        if ($blog->wasChanged([
            'title', 'excerpt', 'content', 'status', 'published_at', 'category_id',
        ])) {
            $this->invalidateCache();
        }
    }

    /**
     * Handle the Blog "deleted" event.
     */
    public function deleted(BlogPost $blog): void
    {
        $this->invalidateCache();
    }

    public function restored(BlogPost $blog): void
    {
        $this->invalidateCache();
    }

    public function forceDeleted(BlogPost $blog): void
    {
        $this->invalidateCache();
    }

    /**
     * Invalidate Steward Agent content cache when blog changes
     */
    private function invalidateCache(): void
    {
        Cache::forget('steward_content_summary');
        
        \Illuminate\Support\Facades\Log::info('Steward Agent content cache invalidated', [
            'reason' => 'Blog post changed',
            'timestamp' => now()
        ]);
    }
}
