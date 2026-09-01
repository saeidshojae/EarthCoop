<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\FounderNajmBaharProjectReviewDraft;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Support\Str;

class FounderNajmBaharProjectReviewDraftService
{
    /** @return array<string,mixed> */
    public function draft(Project $project, ?int $requestedBy = null, ?string $reasonCode = null): array
    {
        $existing = FounderNajmBaharProjectReviewDraft::query()
            ->where('project_id', $project->id)
            ->where('status', 'draft')
            ->latest('id')
            ->first();

        if ($existing) {
            return $this->summary($existing, 'existing');
        }

        $findings = $this->findings($project);
        $summary = $this->summaryText($project, $findings);

        $draft = FounderNajmBaharProjectReviewDraft::query()->create([
            'project_id' => $project->id,
            'requested_by_user_id' => $requestedBy,
            'status' => 'draft',
            'summary' => $summary,
            'findings' => $findings,
            'reason_code' => $reasonCode ? Str::limit($reasonCode, 120, '') : null,
        ]);

        return $this->summary($draft, 'created');
    }

    /** @return array<int,array<string,mixed>> */
    protected function findings(Project $project): array
    {
        $findings = [];
        $requiredText = [
            'title' => 'project_title_missing',
            'summary' => 'project_summary_missing',
            'problem_statement' => 'problem_statement_missing',
            'solution_description' => 'solution_description_missing',
            'value_proposition' => 'value_proposition_missing',
            'target_market' => 'target_market_missing',
            'fund_usage_scope' => 'fund_usage_scope_missing',
            'failure_policy' => 'failure_policy_missing',
        ];
        foreach ($requiredText as $field => $code) {
            if (trim((string) $project->getAttribute($field)) === '') {
                $findings[] = ['severity' => 'warning', 'code' => $code, 'field' => $field];
            }
        }

        if ((int) $project->required_capital <= 0) {
            $findings[] = ['severity' => 'warning', 'code' => 'required_capital_missing_or_zero', 'field' => 'required_capital'];
        }
        if ((int) $project->base_value_min <= 0 || (int) $project->base_value_max <= 0) {
            $findings[] = ['severity' => 'warning', 'code' => 'base_valuation_range_incomplete', 'field' => 'base_value_min/base_value_max'];
        } elseif ((int) $project->base_value_min > (int) $project->base_value_max) {
            $findings[] = ['severity' => 'critical', 'code' => 'base_valuation_range_invalid', 'field' => 'base_value_min/base_value_max'];
        }
        if ((int) $project->total_shares <= 0) {
            $findings[] = ['severity' => 'warning', 'code' => 'total_shares_missing_or_zero', 'field' => 'total_shares'];
        }
        if ((float) $project->initial_auction_percent < 0 || (float) $project->initial_auction_percent > 100) {
            $findings[] = ['severity' => 'critical', 'code' => 'initial_auction_percent_out_of_range', 'field' => 'initial_auction_percent'];
        }
        if ((float) $project->max_user_ownership_percent < 0 || (float) $project->max_user_ownership_percent > 100) {
            $findings[] = ['severity' => 'critical', 'code' => 'max_user_ownership_percent_out_of_range', 'field' => 'max_user_ownership_percent'];
        }
        if (! (bool) $project->accept_transparency) {
            $findings[] = ['severity' => 'warning', 'code' => 'transparency_not_accepted', 'field' => 'accept_transparency'];
        }
        if (! (bool) $project->accept_rules) {
            $findings[] = ['severity' => 'warning', 'code' => 'rules_not_accepted', 'field' => 'accept_rules'];
        }
        if (trim((string) $project->risk_level) === '') {
            $findings[] = ['severity' => 'warning', 'code' => 'risk_level_missing', 'field' => 'risk_level'];
        }
        if (empty($project->main_risks)) {
            $findings[] = ['severity' => 'warning', 'code' => 'main_risks_missing', 'field' => 'main_risks'];
        }

        return $findings;
    }

    /** @param array<int,array<string,mixed>> $findings */
    protected function summaryText(Project $project, array $findings): string
    {
        $critical = count(array_filter($findings, fn (array $item): bool => ($item['severity'] ?? '') === 'critical'));
        $warnings = count(array_filter($findings, fn (array $item): bool => ($item['severity'] ?? '') === 'warning'));

        return sprintf(
            'Najm Hoda project review draft for project #%d (%s): %d critical finding(s), %d warning(s). No project state, valuation, approval, or ledger data was changed.',
            (int) $project->id,
            (string) ($project->title ?: 'untitled'),
            $critical,
            $warnings
        );
    }

    /** @return array<string,mixed> */
    protected function summary(FounderNajmBaharProjectReviewDraft $draft, string $mode): array
    {
        return [
            'success' => true,
            'status' => 'draft_ready',
            'mode' => $mode,
            'draft_id' => (int) $draft->id,
            'project_id' => (int) $draft->project_id,
            'draft_status' => (string) $draft->status,
            'finding_count' => count((array) $draft->findings),
        ];
    }
}
