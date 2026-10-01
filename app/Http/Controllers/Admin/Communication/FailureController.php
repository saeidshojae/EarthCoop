<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationRecipient;
use Illuminate\View\View;

final class FailureController extends Controller
{
    public function index(): View
    {
        $recipients = CommunicationRecipient::query()
            ->with([
                'communication',
                'templateVersion.template',
                'attempts' => fn ($query) => $query->orderBy('attempt_number'),
            ])
            ->whereIn('status', ['retrying', 'failed'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('admin.communications.failures', compact('recipients'));
    }
}
