<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Spring;
use App\Modules\NajmBahar\Adapters\LegacyNajmAdapter;
use Illuminate\Support\Facades\Log;

class ProcessSpringCreatedNajm implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $springId;

    public $tries = 3;

    public function __construct(int $springId)
    {
        $this->springId = $springId;
    }

    public function handle()
    {
        try {
            $spring = Spring::find($this->springId);
            if (!$spring) {
                Log::warning('ProcessSpringCreatedNajm: Spring not found id=' . $this->springId);
                return;
            }

            LegacyNajmAdapter::onSpringCreated($spring);
        } catch (\Throwable $e) {
            Log::error('ProcessSpringCreatedNajm failed: ' . $e->getMessage());
            throw $e; // allow queue retry
        }
    }
}
