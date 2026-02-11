<?php

namespace App\Observers;

use App\Models\KbArticle;
use Illuminate\Support\Facades\Cache;

class KbArticleObserver
{
    /**
     * Handle the KbArticle "created" event.
     */
    public function created(KbArticle $article): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the KbArticle "updated" event.
     */
    public function updated(KbArticle $article): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the KbArticle "deleted" event.
     */
    public function deleted(KbArticle $article): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the KbArticle "restored" event.
     */
    public function restored(KbArticle $article): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the KbArticle "force deleted" event.
     */
    public function forceDeleted(KbArticle $article): void
    {
        $this->invalidateCache();
    }

    /**
     * Invalidate Steward Agent content cache
     */
    private function invalidateCache(): void
    {
        Cache::forget('steward_content_summary');
        
        // Optional: Log the invalidation
        \Illuminate\Support\Facades\Log::info('Steward Agent content cache invalidated', [
            'timestamp' => now(),
            'reason' => 'KbArticle changed'
        ]);
    }
}
