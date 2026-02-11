<?php

namespace App\Observers;

use App\Models\Blog;
use Illuminate\Support\Facades\Cache;

class BlogObserver
{
    /**
     * Handle the Blog "created" event.
     */
    public function created(Blog $blog): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Blog "updated" event.
     */
    public function updated(Blog $blog): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Blog "deleted" event.
     */
    public function deleted(Blog $blog): void
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
