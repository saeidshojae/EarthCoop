<?php

namespace App\Events\Elections;

use App\Models\ElectionRepresentationAssignment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionRepresentationActivated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ElectionRepresentationAssignment $assignment)
    {
    }
}
