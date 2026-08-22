<?php

namespace App\Services\Elections;

use App\Models\Election;
use App\Models\ElectionTrendAlert;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ElectionMeaningfulTrendNotificationService
{
    public function __construct(
        private readonly ElectionCandidateReportService $reports,
        private readonly NotificationService $notifications,
    ) {}

    public function evaluateAndNotify(Election $election, int $candidateUserId, string $position): ?ElectionTrendAlert
    {
        $policy = $election->policyVersion;
        $bucketDays = max(1, (int) ($policy?->report_bucket_days ?? 7));
        $to = CarbonImmutable::now()->startOfDay();
        $from = $to->subDays($bucketDays);

        $report = $this->reports->report($election, $candidateUserId, $position, $from, $to);
        if (($report['details_suppressed'] ?? true) || ! ($report['meaningful_trend'] ?? false)) {
            return null;
        }

        $net = (int) ($report['net_change'] ?? 0);
        if ($net === 0) {
            return null;
        }
        $direction = $net > 0 ? 'up' : 'down';
        $fingerprint = hash('sha256', implode('|', [
            $election->id,
            $candidateUserId,
            $position,
            $from->toDateString(),
            $to->toDateString(),
            $direction,
            abs($net),
            (int) ($report['meaningful_trend_threshold'] ?? 0),
        ]));

        return DB::transaction(function () use ($election, $candidateUserId, $position, $from, $to, $direction, $fingerprint, $report) {
            $alert = ElectionTrendAlert::query()->firstOrCreate([
                'election_id' => $election->id,
                'candidate_user_id' => $candidateUserId,
                'position' => $position,
                'window_start' => $from->toDateString(),
                'window_end' => $to->toDateString(),
                'trend_direction' => $direction,
            ], [
                'fingerprint' => $fingerprint,
                'metadata' => [
                    'source' => 'e0_7_3_meaningful_trend',
                    'bucket_days' => (int) ($report['window']['bucket_days'] ?? 0),
                    'threshold' => (int) ($report['meaningful_trend_threshold'] ?? 0),
                ],
            ]);

            if ($alert->notified_at !== null) {
                return $alert;
            }

            $user = User::query()->find($candidateUserId);
            if ($user === null) {
                return $alert;
            }

            $this->notifications->notifyUser(
                $user,
                'روند معنادار در انتخابات',
                'در بازه تجمیعی اخیر تغییر معناداری در روند رأی شما ثبت شده است. برای بررسی، گزارش تجمیعی و حریم‌محور انتخابات را ببینید.',
                null,
                'info',
                [
                    'election_id' => (int) $election->id,
                    'position' => $position,
                    'trend_alert_id' => (int) $alert->id,
                ],
            );

            $alert->forceFill(['notified_at' => now()])->save();
            return $alert->refresh();
        }, 3);
    }
}
