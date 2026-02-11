<?php

namespace App\Observers;

use App\Models\FaqQuestion;
use Illuminate\Support\Facades\Cache;

class FaqQuestionObserver
{
    /**
     * Handle the FaqQuestion "created" event.
     */
    public function created(FaqQuestion $question): void
    {
        if ($question->is_published && $question->answer) {
            $this->invalidateCache();
        }
    }

    /**
     * Handle the FaqQuestion "updated" event.
     */
    public function updated(FaqQuestion $question): void
    {
        // Invalidate if publishing status changed or answer changed
        if ($question->isDirty(['is_published', 'answer'])) {
            $this->invalidateCache();
        }
    }

    /**
     * Handle the FaqQuestion "deleted" event.
     */
    public function deleted(FaqQuestion $question): void
    {
        $this->invalidateCache();
    }

    /**
     * Invalidate Steward Agent content cache when FAQ changes
     */
    private function invalidateCache(): void
    {
        Cache::forget('steward_content_summary');
        
        \Illuminate\Support\Facades\Log::info('Steward Agent content cache invalidated', [
            'reason' => 'FAQ question changed',
            'timestamp' => now()
        ]);
    }
}
