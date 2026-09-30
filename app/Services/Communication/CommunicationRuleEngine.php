<?php

namespace App\Services\Communication;

use App\Jobs\Communication\EvaluateDelayedCommunicationCondition;
use App\Models\CommunicationRule;
use App\Models\User;
use InvalidArgumentException;

class CommunicationRuleEngine
{
    public function __construct(
        private readonly CommunicationEventRegistry $events,
        private readonly CommunicationAudienceRegistry $audiences,
        private readonly CommunicationDispatcher $dispatcher,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function handleEvent(string $eventKey, array $payload): int
    {
        $event = $this->events->get($eventKey);
        $this->validatePayload($event, $payload);

        $handled = 0;

        foreach (CommunicationRule::query()
            ->where('trigger_type', 'event')
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->orderBy('id')
            ->get() as $rule) {
            $audienceDefinition = (array) $rule->audience_definition;
            $audienceKey = trim((string) ($audienceDefinition['key'] ?? ''));
            $this->audiences->get($audienceKey);

            if ($rule->condition_definition !== null || (int) $rule->delay_seconds > 0) {
                $job = EvaluateDelayedCommunicationCondition::dispatch($rule->id, $payload)
                    ->onQueue('communications-normal');

                if ((int) $rule->delay_seconds > 0) {
                    $job->delay(now()->addSeconds((int) $rule->delay_seconds));
                }

                $handled++;
                continue;
            }

            $recipients = $this->resolveAudience($audienceDefinition, $payload);
            if ($recipients === []) {
                continue;
            }

            $communication = $this->dispatcher->dispatch(
                $rule->template->key,
                ['type' => 'event', 'id' => $eventKey],
                $recipients,
                $payload,
                [
                    'locale' => (string) ($payload['locale'] ?? 'fa'),
                    'priority' => (int) $rule->priority,
                    'deduplication_key' => $this->deduplicationKey($rule, $eventKey, $payload),
                ],
            );

            if ($communication->communication_rule_id === null) {
                $communication->update(['communication_rule_id' => $rule->id]);
            }

            $handled++;
        }

        return $handled;
    }

    /** @param array<string,mixed> $event @param array<string,mixed> $payload */
    private function validatePayload(array $event, array $payload): void
    {
        foreach ((array) ($event['required_payload'] ?? []) as $key) {
            if (! array_key_exists((string) $key, $payload)) {
                throw new InvalidArgumentException("Missing required communication event payload: {$key}");
            }
        }
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $payload @return array<int,User> */
    private function resolveAudience(array $definition, array $payload): array
    {
        return match ((string) ($definition['key'] ?? '')) {
            'event.user' => $this->usersByIds([(int) ($payload['user_id'] ?? 0)]),
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

    /** @param array<string,mixed> $payload */
    private function deduplicationKey(CommunicationRule $rule, string $eventKey, array $payload): string
    {
        ksort($payload);

        return 'rule:'.$rule->id.':event:'.$eventKey.':'.hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
