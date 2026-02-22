<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaAdaptivePolicyLearningService;
use Illuminate\Console\Command;

class NajmHodaPolicyLearningLoop extends Command
{
    protected $signature = 'najm-hoda:policy-learning-loop
        {--window=24 : Analysis window hours}
        {--apply : Apply adaptive override}
        {--clear : Clear current adaptive override}
        {--list-pending : List pending recommendations}
        {--review-id= : Recommendation id for operator review}
        {--decision= : Review decision (approve|reject)}
        {--reason= : Optional reason for review decision}';

    protected $description = 'Run adaptive safety/policy learning loop based on drift and governance metrics';

    public function __construct(
        protected NajmHodaAdaptivePolicyLearningService $policyLearningService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Policy-learning loop skipped.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('clear')) {
            $this->policyLearningService->clearOverride(auth()->id(), 'manual_clear_from_command');
            $this->info('Adaptive override cleared.');
            return self::SUCCESS;
        }

        if ((bool) $this->option('list-pending')) {
            $rows = $this->policyLearningService->listRecommendations('pending', 50);
            $table = array_map(static function (array $row): array {
                return [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    implode(',', (array) ($row['reason_codes'] ?? [])),
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['expires_at'] ?? ''),
                ];
            }, $rows);
            $this->table(['id', 'status', 'reason_codes', 'created_at', 'expires_at'], $table);
            return self::SUCCESS;
        }

        $reviewId = trim((string) $this->option('review-id'));
        if ($reviewId !== '') {
            $decision = trim((string) $this->option('decision'));
            if (!in_array($decision, ['approve', 'reject'], true)) {
                $this->error('For --review-id, --decision must be approve or reject.');
                return self::FAILURE;
            }

            $result = $this->policyLearningService->reviewRecommendation(
                $reviewId,
                $decision,
                auth()->id(),
                $this->option('reason') !== null ? (string) $this->option('reason') : null
            );

            if (!(bool) ($result['success'] ?? false)) {
                $this->error('Review failed: ' . (string) ($result['reason'] ?? 'unknown'));
                return self::FAILURE;
            }

            $this->info('Recommendation reviewed successfully.');
            $this->table(['Key', 'Value'], [
                ['recommendation_id', (string) data_get($result, 'recommendation.id', '')],
                ['decision', (string) data_get($result, 'recommendation.decision', '')],
                ['status', (string) data_get($result, 'recommendation.status', '')],
                ['applied', (bool) ($result['applied'] ?? false) ? 'yes' : 'no'],
            ]);

            return self::SUCCESS;
        }

        $window = is_numeric($this->option('window')) ? (int) $this->option('window') : 24;
        $apply = (bool) $this->option('apply');
        $report = $this->policyLearningService->analyze($window, $apply);

        $this->table(['Key', 'Value'], [
            ['window_hours', (string) ($report['window_hours'] ?? '')],
            ['drift_status', (string) data_get($report, 'input.policy_drift_status', '')],
            ['drift_rate', (string) data_get($report, 'input.policy_drift_rate', '')],
            ['success_rate', (string) data_get($report, 'input.auto_action_success_rate', '')],
            ['should_apply', (bool) ($report['should_apply'] ?? false) ? 'yes' : 'no'],
            ['applied', (bool) ($report['applied'] ?? false) ? 'yes' : 'no'],
            ['recommendation_id', (string) ($report['recommendation_id'] ?? '')],
            ['review_status', (string) data_get($report, 'review.status', '')],
            ['max_actions_per_run', (string) data_get($report, 'recommended_override.max_actions_per_run', '')],
            ['allow_apply_low_risk', data_get($report, 'recommended_override.allow_apply_low_risk', null) === true ? 'true' : 'false'],
        ]);

        return self::SUCCESS;
    }
}
