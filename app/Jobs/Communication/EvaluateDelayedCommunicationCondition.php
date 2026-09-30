<?php

namespace App\Jobs\Communication;

use App\Models\CommunicationRule;
use App\Models\User;
use App\Services\Communication\CommunicationAudienceRegistry;
use App\Services\Communication\CommunicationConditionRegistry;
use App\Services\Communication\CommunicationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class EvaluateDelayedCommunicationCondition implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly int $ruleId,
        public readonly array $payload,
    ) {
    }

    public function handle(
        CommunicationConditionRegistry $conditions,
        CommunicationAudienceRegistry $audiences,
        CommunicationDispatcher $dispatcher,
    ): void {
        $rule = CommunicationRule::query()->whereKey($this->ruleId)->where('is_active', true)->first();
        if (! $rule) {
            return;
        }

        $definition = (array) $rule->condition_definition;
        $conditionKey = trim((string) ($definition['key'] ?? ''));
        $conditions->get($conditionKey);

        if (! $this->conditionMatches($conditionKey)) {
            return;
        }

        $audienceDefinition = (array) $rule->audience_definition;
        $audienceKey = trim((string) ($audienceDefinition['key'] ?? ''));
        $audiences->get($audienceKey);
        $recipients = $this->resolveAudience($audienceDefinition);

        if ($recipients === []) {
            return;
        }

        $communication = $dispatcher->dispatch(
            $rule->template->key,
            ['type' => 'rule', 'id' => (string) $rule->id],
            $recipients,
            $this->payload,
            [
                'locale' => (string) ($this->payload['locale'] ?? 'fa'),
                'priority' => (int) $rule->priority,
                'deduplication_key' => $this->deduplicationKey($rule),
            ],
        );

        if ($communication->communication_rule_id === null) {
            $communication->update(['communication_rule_id' => $rule->id]);
        }
    }

    private function conditionMatches(string $conditionKey): bool
    {
        $userId = (int) ($this->payload['user_id'] ?? 0);
        $user = $userId > 0 ? User::query()->find($userId) : null;

        return match ($conditionKey) {
            'user.email_verified' => $user !== null && $user->email_verified_at !== null,
            'user.registration_complete' => $user !== null,
            default => throw new InvalidArgumentException("Unsupported communication condition evaluator: {$conditionKey}"),
        };
    }

    /** @param array<string,mixed> $definition @return array<int,User> */
    private function resolveAudience(array $definition): array
    {
        return match ((string) ($definition['key'] ?? '')) {
            'event.user' => $this->usersByIds([(int) ($this->payload['user_id'] ?? 0)]),
            'specific.user' => $this->usersByIds(array_map('intval', (array) ($definition['user_ids'] ?? []))),
            default => throw new InvalidArgumentException('Unsupported communication audience resolver.'),
        };
    }

    /** @param array<int,int> $ids @return array<int,User> */
    private function usersByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_unique($ids), fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->orderBy('id')->get()->all();
    }

    private function deduplicationKey(CommunicationRule $rule): string
    {
        $payload = $this->payload;
        ksort($payload);

        return 'rule:'.$rule->id.':delayed:'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
