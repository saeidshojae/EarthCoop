<?php

namespace App\Jobs\Communication;

use App\Models\CommunicationRun;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\Context\WeeklyInspectorReportContextBuilder;
use App\Services\Communication\Context\WeeklyManagerReportContextBuilder;
use App\Services\Communication\Context\WeeklyMemberReportContextBuilder;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class ResolveCommunicationRunAudience implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $runId,
        public readonly int $chunkSize = 500,
        public readonly ?int $offset = null,
    ) {
    }

    public function handle(CommunicationAudienceRegistry $audiences): void
    {
        $run = CommunicationRun::query()->with('rule.template')->find($this->runId);
        $rule = $run?->rule;
        if (! $run || ! $rule || ! $rule->is_active) {
            return;
        }

        $definition = (array) $rule->audience_definition;
        $audienceKey = trim((string) ($definition['key'] ?? ''));
        $audiences->get($audienceKey);

        if ($audienceKey === 'specific.user') {
            $this->resolveSpecificUsers($run, $definition);

            return;
        }

        if (! in_array($audienceKey, ['role.member', 'role.manager', 'role.inspector'], true)) {
            throw new InvalidArgumentException('Scheduled audience resolver is not implemented for this registered audience.');
        }

        $this->resolveRoleAudience($run, $audienceKey);
    }

    /** @param array<string,mixed> $definition */
    private function resolveSpecificUsers(CommunicationRun $run, array $definition): void
    {
        $rule = $run->rule;
        $ids = array_values(array_filter(
            array_unique(array_map('intval', (array) ($definition['user_ids'] ?? []))),
            fn (int $id): bool => $id > 0,
        ));
        $chunkSize = max(1, $this->chunkSize);

        if ($this->offset === null) {
            for ($offset = 0; $offset < count($ids); $offset += $chunkSize) {
                self::dispatch($run->id, $chunkSize, $offset)->onQueue('communications-bulk');
            }

            return;
        }

        $chunkIds = array_slice($ids, $this->offset, $chunkSize);
        if ($chunkIds === []) {
            return;
        }

        $recipients = User::query()->whereIn('id', $chunkIds)->orderBy('id')->get();
        if ($recipients->isEmpty()) {
            return;
        }

        $communication = app(CommunicationDispatcher::class)->dispatch(
            $rule->template->key,
            ['type' => 'scheduled_rule', 'id' => (string) $rule->id],
            $recipients,
            [],
            [
                'priority' => (int) $rule->priority,
                'delivery_class' => 'bulk',
                'deduplication_key' => 'run:'.$run->id.':offset:'.$this->offset,
            ],
        );

        $this->attachAndCount($run, $communication, $recipients->count());
    }

    private function resolveRoleAudience(CommunicationRun $run, string $audienceKey): void
    {
        $chunkSize = max(1, $this->chunkSize);
        $query = $this->roleAudienceQuery($audienceKey);

        if ($this->offset === null) {
            $count = (clone $query)->count();
            for ($offset = 0; $offset < $count; $offset += $chunkSize) {
                self::dispatch($run->id, $chunkSize, $offset)->onQueue('communications-bulk');
            }

            return;
        }

        $users = (clone $query)
            ->orderBy('users.id')
            ->offset($this->offset)
            ->limit($chunkSize)
            ->get();

        foreach ($users as $user) {
            $context = $this->weeklyContext($run->rule->template->key, $audienceKey, $user, $run);
            if ($context === null) {
                continue;
            }

            $communication = app(CommunicationDispatcher::class)->dispatch(
                $run->rule->template->key,
                ['type' => 'scheduled_rule', 'id' => (string) $run->rule->id],
                [$user],
                $context,
                [
                    'priority' => (int) $run->rule->priority,
                    'delivery_class' => 'bulk',
                    'deduplication_key' => 'run:'.$run->id.':user:'.$user->id.':'.$run->rule->template->key,
                ],
            );

            $this->attachAndCount($run, $communication, 1);
        }
    }

    private function roleAudienceQuery(string $audienceKey)
    {
        $query = User::query()
            ->where('status', 'active')
            ->where('is_system', false);

        if ($audienceKey === 'role.member') {
            return $query->whereNotIn('id', $this->currentResponsibilityUserIdsQuery());
        }

        $role = $audienceKey === 'role.manager' ? 3 : 2;

        return $query->whereIn('id', $this->currentResponsibilityUserIdsQuery($role));
    }

    private function currentResponsibilityUserIdsQuery(?int $role = null)
    {
        $query = GroupUser::query()
            ->select('user_id')
            ->where('status', 1)
            ->whereIn('role', $role === null ? [2, 3] : [$role])
            ->where(function ($membershipQuery): void {
                $membershipQuery->whereNull('expired')
                    ->orWhere('expired', 0)
                    ->orWhere('expired', '>', now());
            })
            ->distinct();

        return $query;
    }

    /** @return array<string,mixed>|null */
    private function weeklyContext(
        string $templateKey,
        string $audienceKey,
        User $user,
        CommunicationRun $run,
    ): ?array {
        $timezone = trim((string) ($run->rule->schedule?->timezone ?? '')) !== ''
            ? (string) $run->rule->schedule->timezone
            : 'UTC';
        $anchor = ($run->scheduled_for ?? $run->started_at ?? now())
            ->copy()
            ->setTimezone($timezone);
        $period = CarbonPeriod::create(
            $anchor->copy()->subDays(7)->startOfDay(),
            $anchor->copy()->subDay()->endOfDay(),
        );

        if ($templateKey === 'reports.member.weekly' || $audienceKey === 'role.member') {
            return app(WeeklyMemberReportContextBuilder::class)->build($user, $period);
        }

        if ($templateKey === 'reports.manager.weekly' || $audienceKey === 'role.manager') {
            $context = app(WeeklyManagerReportContextBuilder::class)->build($user, $period);

            return $context['managed_groups_count'] > 0 ? $context : null;
        }

        if ($templateKey === 'reports.inspector.weekly' || $audienceKey === 'role.inspector') {
            $context = app(WeeklyInspectorReportContextBuilder::class)->build($user, $period);

            return $context['inspected_groups_count'] > 0 ? $context : null;
        }

        throw new InvalidArgumentException('Role audience requires a registered weekly report context.');
    }

    private function attachAndCount(CommunicationRun $run, $communication, int $matchedCount): void
    {
        $alreadyAttached = (int) ($communication->communication_run_id ?? 0) === (int) $run->id;
        $communication->update([
            'communication_rule_id' => $run->rule->id,
            'communication_run_id' => $run->id,
        ]);

        if ($alreadyAttached) {
            return;
        }

        $counts = $communication->recipients()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $run->increment('matched_count', $matchedCount);
        $run->increment('eligible_count', (int) ($counts['queued'] ?? 0));
        $run->increment('suppressed_count', (int) ($counts['suppressed'] ?? 0));
        $run->increment('invalid_count', (int) ($counts['invalid'] ?? 0));
        $run->increment('queued_count', (int) ($counts['queued'] ?? 0));
    }
}
