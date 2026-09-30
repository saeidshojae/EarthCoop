<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationRecipient;
use App\Models\CommunicationRule;
use App\Models\CommunicationRuleSchedule;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function index(): View
    {
        $now = now();

        $metrics = [
            'queued' => CommunicationRecipient::query()->where('status', 'queued')->count(),
            'sent' => CommunicationRecipient::query()->where('status', 'sent')->count(),
            'retrying' => CommunicationRecipient::query()->where('status', 'retrying')->count(),
            'failed' => CommunicationRecipient::query()->where('status', 'failed')->count(),
            'active_rules' => CommunicationRule::query()->where('is_active', true)->count(),
            'due_runs' => CommunicationRuleSchedule::query()
                ->whereHas('rule', fn ($query) => $query->where('is_active', true))
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', $now)
                ->count(),
            'upcoming_runs' => CommunicationRuleSchedule::query()
                ->whereHas('rule', fn ($query) => $query->where('is_active', true))
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '>', $now)
                ->count(),
        ];

        return view('admin.communications.dashboard', compact('metrics'));
    }
}
