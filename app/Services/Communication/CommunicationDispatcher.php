<?php

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\DeliveryStatus;
use App\Jobs\Communication\DeliverCommunicationRecipient;
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
        [$deduplicationKey, $locale, $template, $version] = $this->resolveDispatch(
            $purposeKey,
            $context,
            $options,
        );

        if ($deduplicationKey !== null && ($existing = $this->existingByDedupe($deduplicationKey))) {
            return $existing;
        }

        try {
            $communication = DB::transaction(function () use (
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
                $communication = $this->createCommunication(
                    $source,
                    $context,
                    $options,
                    $deduplicationKey,
                    $template,
                    $version,
                );

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
            if ($deduplicationKey !== null && ($existing = $this->existingByDedupe($deduplicationKey))) {
                return $existing;
            }

            throw $exception;
        }

        $this->queueEligibleRecipients($communication, $template->classification, $options);

        return $communication->refresh();
    }

    /**
     * Dispatch to email addresses that do not need an EarthCoop user account.
     * Optional communications fail closed unless explicit consent is supplied.
     *
     * @param array<string,mixed> $source
     * @param iterable<int,array{email:string,locale?:string}> $recipients
     * @param array<string,mixed> $context
     * @param array<string,mixed> $options
     */
    public function dispatchExternal(
        string $purposeKey,
        array $source,
        iterable $recipients,
        array $context,
        array $options = [],
    ): Communication {
        [$deduplicationKey, $locale, $template, $version] = $this->resolveDispatch(
            $purposeKey,
            $context,
            $options,
        );

        if ($deduplicationKey !== null && ($existing = $this->existingByDedupe($deduplicationKey))) {
            return $existing;
        }

        try {
            $communication = DB::transaction(function () use (
                $source,
                $recipients,
                $context,
                $options,
                $deduplicationKey,
                $locale,
                $template,
                $version,
            ): Communication {
                $communication = $this->createCommunication(
                    $source,
                    $context,
                    $options,
                    $deduplicationKey,
                    $template,
                    $version,
                );

                foreach ($recipients as $recipient) {
                    if (! is_array($recipient)) {
                        throw new InvalidArgumentException('External communication recipients must be structured arrays.');
                    }

                    $email = trim((string) ($recipient['email'] ?? ''));
                    $recipientLocale = trim((string) ($recipient['locale'] ?? $locale)) ?: $locale;
                    if ($recipientLocale !== $locale) {
                        throw new InvalidArgumentException('External recipient locale must match the communication template locale.');
                    }

                    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $decision = new PreferenceDecision(false, 'invalid_email');
                        $status = DeliveryStatus::Invalid;
                    } elseif (
                        $template->classification === CommunicationClassification::Optional
                        && ! (bool) ($options['external_consent'] ?? false)
                    ) {
                        $decision = new PreferenceDecision(false, 'external_optional_consent_required');
                        $status = DeliveryStatus::Suppressed;
                    } else {
                        $decision = new PreferenceDecision(
                            true,
                            'external_'.$template->classification->value,
                        );
                        $status = DeliveryStatus::Pending;
                    }

                    $communication->recipients()->create([
                        'user_id' => null,
                        'email' => $email,
                        'locale' => $recipientLocale,
                        'communication_template_version_id' => $version->id,
                        'preference_decision' => $decision->toArray(),
                        'status' => $status,
                    ]);
                }

                return $communication->refresh();
            });
        } catch (QueryException $exception) {
            if ($deduplicationKey !== null && ($existing = $this->existingByDedupe($deduplicationKey))) {
                return $existing;
            }

            throw $exception;
        }

        $this->queueEligibleRecipients($communication, $template->classification, $options);

        return $communication->refresh();
    }

    /** @param array<string,mixed> $context @param array<string,mixed> $options */
    private function resolveDispatch(string $purposeKey, array $context, array $options): array
    {
        $deduplicationKey = isset($options['deduplication_key'])
            ? trim((string) $options['deduplication_key'])
            : null;
        $deduplicationKey = $deduplicationKey !== '' ? $deduplicationKey : null;

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

        return [$deduplicationKey, $locale, $template, $version];
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $context @param array<string,mixed> $options */
    private function createCommunication(
        array $source,
        array $context,
        array $options,
        ?string $deduplicationKey,
        CommunicationTemplate $template,
        CommunicationTemplateVersion $version,
    ): Communication {
        return Communication::query()->create([
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
    }

    private function existingByDedupe(string $deduplicationKey): ?Communication
    {
        return Communication::query()
            ->where('deduplication_key', $deduplicationKey)
            ->first();
    }

    /** @param array<string,mixed> $options */
    private function queueEligibleRecipients(
        Communication $communication,
        CommunicationClassification $classification,
        array $options,
    ): void {
        $queue = $this->queueName($classification, $options);
        $queuedRecipientIds = [];

        foreach ($communication->recipients()->where('status', DeliveryStatus::Pending->value)->get() as $recipient) {
            $claimed = $communication->recipients()
                ->whereKey($recipient->id)
                ->where('status', DeliveryStatus::Pending->value)
                ->update([
                    'status' => DeliveryStatus::Queued->value,
                    'queued_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($claimed === 1) {
                $queuedRecipientIds[] = $recipient->id;
            }
        }

        if ($queuedRecipientIds === []) {
            return;
        }

        // Persist aggregate state before dispatch. This remains correct even with a sync queue driver.
        $communication->update(['status' => CommunicationStatus::Queued]);

        foreach ($queuedRecipientIds as $recipientId) {
            DeliverCommunicationRecipient::dispatch($recipientId)->onQueue($queue);
        }
    }

    /** @param array<string,mixed> $options */
    private function queueName(
        CommunicationClassification $classification,
        array $options,
    ): string {
        if ($classification === CommunicationClassification::Required) {
            return 'communications-critical';
        }

        if (($options['delivery_class'] ?? null) === 'bulk') {
            return 'communications-bulk';
        }

        return 'communications-normal';
    }
}
