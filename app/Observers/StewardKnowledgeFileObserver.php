<?php

namespace App\Observers;

use App\Models\StewardKnowledgeFile;
use Illuminate\Support\Facades\Cache;

class StewardKnowledgeFileObserver
{
    /**
     * Handle the StewardKnowledgeFile "created" event.
     */
    public function created(StewardKnowledgeFile $stewardKnowledgeFile): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the StewardKnowledgeFile "updated" event.
     */
    public function updated(StewardKnowledgeFile $stewardKnowledgeFile): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the StewardKnowledgeFile "deleted" event.
     */
    public function deleted(StewardKnowledgeFile $stewardKnowledgeFile): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the StewardKnowledgeFile "forceDeleted" event.
     */
    public function forceDeleted(StewardKnowledgeFile $stewardKnowledgeFile): void
    {
        $this->invalidateCache();
    }

    /**
     * پاک‌کردن کش محتوای Steward
     */
    protected function invalidateCache(): void
    {
        Cache::forget('steward_content_summary');
    }
}
