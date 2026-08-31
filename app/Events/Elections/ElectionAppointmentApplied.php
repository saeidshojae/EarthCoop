<?php

namespace App\Events\Elections;

use App\Models\ElectionAppointment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionAppointmentApplied implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ElectionAppointment $appointment)
    {
    }
}
