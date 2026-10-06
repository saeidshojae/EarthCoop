<?php

namespace App\Services\Communication;

use App\Enums\Communication\DeliveryStatus;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplateVersion;
use App\Models\GroupUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use RuntimeException;

class CommunicationCampaignService
{
    public function __construct(
        private readonly CommunicationAudienceRegistry $audiences,
        private readonly CommunicationPreferenceService $preferences,
        private readonly CommunicationTemplateRenderer $renderer,
        private readonly CommunicationDispatcher $dispatcher,
    ) {
    }

    /** @return array{matched:int,eligible:int,suppressed:int,invalid:int} */
    public function preview(CommunicationCampaign $campaign): array
    {
        $counts = [
            'matched' => 0,
            'eligible' => 0,
            'suppressed' => 0,
            'invalid' => 0,
        ];

        $this->audienceQuery($campaign)->orderBy('users.id')->chunkById(
            max(1, (int) config('communications.campaigns.preview_chunk_size', 500)),
            function ($users) use (&$counts, $campaign): void {
                foreach ($users as $user) {
                    $counts['matched']++;
                    $email = trim((string) ($user->email ?? ''));

                    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $counts['invalid']++;
                        continue;
                    }

                    $decision = $this->preferences->decide(
                        $user,
                        $campaign->template->key,
                        $campaign->classification,
                    );

                    if ($decision->allowed) {
                        $counts['eligible']++;
                    } else {
                        $counts['suppressed']++;
                    }
                }
            },
            'users.id',
            'id',
        );

        return $counts;
    }

    /** @param array<string,mixed> $context @return array{subject:string,body:string} */
    public function sampleRender(
        CommunicationCampaign $campaign,
        User $recipient,
        array $context,
        string $locale = 'fa',
    ): array {
        if (! $this->audienceContains($campaign, $recipient->id)) {
            throw new InvalidArgumentException('Campaign sample recipient is outside the configured audience.');
        }

        $version = $this->publishedVersion($campaign, $locale);

        return $this->renderer->render($version, $context);
    }

    public function confirm(
        CommunicationCampaign $campaign,
        User $actor,
        bool $elevatedConfirmed = false,
    ): CommunicationCampaign {
        if (! in_array($campaign->status, ['draft', 'preview'], true)) {
            throw new InvalidArgumentException('campaign_not_confirmable');
        }

        $preview = $this->preview($campaign);
        $threshold = max(1, (int) config('communications.campaigns.elevated_confirmation_threshold', 1000));

        if ($preview['matched'] >= $threshold && ! $elevatedConfirmed) {
            throw new InvalidArgumentException('elevated_confirmation_required');
        }

        $scheduled = $campaign->scheduled_at !== null && $campaign->scheduled_at->isFuture();
        $campaign->update([
            'status' => $scheduled ? 'scheduled' : 'running',
            'confirmed_at' => now(),
            'approved_by' => $actor->id,
            'paused_at' => null,
            'cancelled_at' => null,
        ]);

        return $campaign->fresh();
    }

    public function processDueCampaigns(CarbonInterface $now, int $limit = 100): int
    {
        $clock = $now->copy()->utc();
        $processed = 0;

        CommunicationCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $clock)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (CommunicationCampaign $campaign) use ($clock, &$processed): void {
                if ($this->startIfDue($campaign, $clock)) {
                    $processed++;
                }
            });

        return $processed;
    }

    public function startIfDue(CommunicationCampaign $campaign, ?CarbonInterface $now = null): bool
    {
        if ($campaign->status !== 'scheduled') {
            return false;
        }

        $clock = ($now ?? CarbonImmutable::now('UTC'))->copy()->utc();
        $scheduledAt = $campaign->scheduled_at?->copy()->utc();

        if ($scheduledAt !== null && $scheduledAt->gt($clock)) {
            return false;
        }

        $updated = CommunicationCampaign::query()
            ->whereKey($campaign->id)
            ->where('status', 'scheduled')
            ->where(function ($query) use ($clock): void {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', $clock);
            })
            ->update([
                'status' => 'running',
                'updated_at' => $clock,
            ]);

        return $updated === 1;
    }

    public function pause(CommunicationCampaign $campaign): CommunicationCampaign
    {
        if (! in_array($campaign->status, ['running', 'scheduled'], true)) {
            throw new InvalidArgumentException('campaign_not_pausable');
        }

        $campaign->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $campaign->fresh();
    }

    public function cancel(CommunicationCampaign $campaign): CommunicationCampaign
    {
        if (in_array($campaign->status, ['completed', 'cancelled'], true)) {
            return $campaign->fresh();
        }

        $campaign->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $campaign->communications()
            ->with('recipients')
            ->get()
            ->each(function ($communication): void {
                $communication->recipients()
                    ->whereIn('status', [
                        DeliveryStatus::Pending->value,
                        DeliveryStatus::Queued->value,
                        DeliveryStatus::Retrying->value,
                    ])
                    ->update([
                        'status' => DeliveryStatus::Cancelled->value,
                        'updated_at' => now(),
                    ]);
            });

        return $campaign->fresh();
    }

    /**
     * @param array<int,int> $userIds
     * @param array<int,array<string,mixed>> $contextsByUserId
     */
    public function dispatchChunk(
        CommunicationCampaign $campaign,
        array $userIds,
        array $contextsByUserId,
    ): int {
        $campaign = CommunicationCampaign::query()->findOrFail($campaign->id);
        if ($campaign->status !== 'running') {
            return 0;
        }

        $requestedIds = array_values(array_filter(
            array_unique(array_map('intval', $userIds)),
            static fn (int $id): bool => $id > 0,
        ));
        if ($requestedIds === []) {
            return 0;
        }

        $allowedIds = $this->audienceQuery($campaign)
            ->whereIn('users.id', $requestedIds)
            ->pluck('users.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $queued = 0;
        foreach (User::query()->whereIn('id', $allowedIds)->orderBy('id')->get() as $user) {
            $context = $contextsByUserId[$user->id] ?? [];
            $deduplicationKey = 'campaign:'.$campaign->id.':user:'.$user->id;

            $existing = $campaign->communications()
                ->where('deduplication_key', $deduplicationKey)
                ->first();
            if ($existing) {
                continue;
            }

            $communication = $this->dispatcher->dispatch(
                $campaign->template->key,
                ['type' => 'campaign', 'id' => (string) $campaign->id],
                [$user],
                $context,
                [
                    'priority' => (int) $campaign->priority,
                    'delivery_class' => 'bulk',
                    'deduplication_key' => $deduplicationKey,
                ],
            );

            $communication->update(['communication_campaign_id' => $campaign->id]);

            if ($communication->recipients()
                ->whereIn('status', [DeliveryStatus::Queued->value, DeliveryStatus::Sent->value])
                ->exists()) {
                $queued++;
            }
        }

        return $queued;
    }

    private function publishedVersion(CommunicationCampaign $campaign, string $locale): CommunicationTemplateVersion
    {
        $version = CommunicationTemplateVersion::query()
            ->where('communication_template_id', $campaign->communication_template_id)
            ->where('locale', $locale)
            ->whereNotNull('published_at')
            ->orderByDesc('version')
            ->first();

        if (! $version) {
            throw new RuntimeException('campaign_template_version_not_available');
        }

        return $version;
    }

    private function audienceContains(CommunicationCampaign $campaign, int $userId): bool
    {
        return $this->audienceQuery($campaign)
            ->where('users.id', $userId)
            ->exists();
    }

    /** @return Builder<User> */
    private function audienceQuery(CommunicationCampaign $campaign): Builder
    {
        $definition = (array) $campaign->audience_definition;
        $key = trim((string) ($definition['key'] ?? ''));
        $this->audiences->get($key);

        $query = User::query();

        if ($key === 'specific.user') {
            $ids = array_values(array_filter(
                array_unique(array_map('intval', (array) ($definition['user_ids'] ?? []))),
                static fn (int $id): bool => $id > 0,
            ));

            return $query->whereIn('users.id', $ids);
        }

        if (! in_array($key, ['role.member', 'role.manager', 'role.inspector'], true)) {
            throw new InvalidArgumentException('Campaign audience resolver is not implemented for this registered audience.');
        }

        $query->where('users.status', 'active')->where('users.is_system', false);
        $responsibilityIds = GroupUser::query()
            ->select('user_id')
            ->where('status', 1)
            ->whereIn('role', $key === 'role.member' ? [2, 3] : [$key === 'role.manager' ? 3 : 2])
            ->where(function ($membershipQuery): void {
                $membershipQuery->whereNull('expired')
                    ->orWhere('expired', 0)
                    ->orWhere('expired', '>', now());
            })
            ->distinct();

        return $key === 'role.member'
            ? $query->whereNotIn('users.id', $responsibilityIds)
            : $query->whereIn('users.id', $responsibilityIds);
    }
}
