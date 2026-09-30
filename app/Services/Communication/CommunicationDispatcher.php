<?php

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\DeliveryStatus;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class CommunicationDispatcher
{
    public function __construct(
        private readonly CommunicationPreferenceService $preferences,
        private readonly CommunicationTemplateRenderer $renderer,
    ) {
    }

    /**
     * @param array<string,mixed> $source
     * @param iterable<int,User> $recipients
     * @param array<string,mixed> $context
     * @param array<string,mixed> $options
     */
    public function dispatch(
        string $purposeKey,
        array $source,
        iterable $recipients,
        array $context,
        array $options = [],
    ): Communication {
        $deduplicationKey = isset($options['deduplication_key'])
            ? trim((string) $options['deduplication_key'])
            : null;
        $deduplicationKey = $deduplicationKey !== '' ? $deduplicationKey : null;

        if ($deduplicationKey !== null) {
            $existing = Communication::query()
                ->where('deduplication_key', $deduplicationKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $locale = trim((string) ($options['locale'] ?? 'fa')) ?: 'fa';
        $template = CommunicationTemplate::query()
            ->where('key', $purposeKey)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            throw new RuntimeException("communication_template_not_available:{$purposeKey}");
        }

        $version = CommunicationTemplateVersion::query()
            ->where('communication_template_id', $template->id)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        if (! $version) {
            throw new RuntimeException("communication_template_version_not_available:{$purposeKey}:{$locale}");
        }

        // Validate the exact immutable version/context pair before persisting work.
        $this->renderer->render($version, $context);

        try {
            return DB::transaction(function () use (
                $purposeKey,
                $source,
                $recipients,
                $context,
                $options,
                $deduplicationKey,
                $locale,
                $template,
                $version,
            ): Communication {
                $communication = Communication::query()->create([
                    'source_type' => isset($source['type']) ? (string) $source['type'] : null,
                    'source_id' => isset($source['id']) ? (string) $source['id'] : null,
                    'communication_template_version_id' => $version->id,
                    'classification' => $template->classification,
                    'priority' => (int) ($options['priority'] ?? 2),
                    'context_snapshot' => $context,
                    'status' => CommunicationStatus::Pending,
                    'deduplication_key' => $deduplicationKey,
                    'scheduled_at' => $options['scheduled_at'] ?? null,
                ]);

                foreach ($recipients as $recipient) {
                    if (! $recipient instanceof User) {
                        throw new InvalidArgumentException('Communication recipients must be User models.');
                    }

                    $email = trim((string) ($recipient->email ?? ''));
                    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $decision = new PreferenceDecision(false, 'invalid_email');
                        $status = DeliveryStatus::Invalid;
                    } else {
                        $decision = $this->preferences->decide(
                            $recipient,
                            $purposeKey,
                            $template->classification,
                        );
                        $status = $decision->allowed
                            ? DeliveryStatus::Pending
                            : DeliveryStatus::Suppressed;
                    }

                    $communication->recipients()->create([
                        'user_id' => $recipient->id,
                        'email' => $email,
                        'locale' => $locale,
                        'communication_template_version_id' => $version->id,
                        'preference_decision' => $decision->toArray(),
                        'status' => $status,
                    ]);
                }

                return $communication->refresh();
            });
        } catch (QueryException $exception) {
            if ($deduplicationKey !== null) {
                $existing = Communication::query()
                    ->where('deduplication_key', $deduplicationKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }
}
