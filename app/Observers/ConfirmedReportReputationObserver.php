<?php

namespace App\Observers;

use App\Models\Blog;
use App\Models\Message;
use App\Models\Poll;
use App\Models\PrivateChatReport;
use App\Models\Report;
use App\Models\ReportedMessage;
use App\Models\User;
use App\Services\ReputationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConfirmedReportReputationObserver
{
    public function __construct(protected ReputationService $reputationService)
    {
    }

    public function updated(Model $report): void
    {
        if (! $report->wasChanged('status') || ! $this->isConfirmedResolution($report)) {
            return;
        }

        $user = $this->reportedUser($report);
        if (! $user) {
            Log::notice('Resolved report has no reputation recipient', [
                'report_model' => $report::class,
                'report_id' => $report->getKey(),
            ]);
            return;
        }

        $sourceType = match (true) {
            $report instanceof ReportedMessage => 'reported_message',
            $report instanceof PrivateChatReport => 'private_chat_report',
            default => 'report',
        };

        try {
            $this->reputationService->applyAction(
                $user,
                'report_received',
                [
                    'report_model' => $report::class,
                    'report_id' => $report->getKey(),
                    'resolution_status' => $report->status,
                ],
                $report->getKey(),
                'moderation.report',
                'report_received:' . $sourceType . ':' . $report->getKey() . ':user:' . $user->id
            );
        } catch (Throwable $e) {
            // Moderation resolution remains authoritative even if reputation recording fails.
            Log::warning('Confirmed report reputation penalty failed', [
                'report_model' => $report::class,
                'report_id' => $report->getKey(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isConfirmedResolution(Model $report): bool
    {
        if ($report instanceof ReportedMessage) {
            return in_array($report->status, ['resolved_by_group_manager', 'resolved_by_admin'], true);
        }

        if ($report instanceof Report || $report instanceof PrivateChatReport) {
            return $report->status === 'resolved';
        }

        return false;
    }

    private function reportedUser(Model $report): ?User
    {
        if ($report instanceof PrivateChatReport) {
            return $report->reportedUser()->first();
        }

        if ($report instanceof ReportedMessage) {
            return $report->message()->with('user')->first()?->user;
        }

        if (! $report instanceof Report) {
            return null;
        }

        return match ($report->type) {
            'user' => User::find($report->reported_item_id),
            'message' => Message::with('user')->find($report->reported_item_id)?->user,
            'post' => Blog::with('user')->find($report->reported_item_id)?->user,
            'poll' => Poll::with('creator')->find($report->reported_item_id)?->creator,
            default => null,
        };
    }
}
