<?php

namespace App\Services\Notifications;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Services\SystemIdentityService;
use Illuminate\Support\Facades\DB;

class AnnouncementManagementService
{
    public function __construct(protected SystemIdentityService $systemIdentities) {}

    /** @param array<string,mixed> $attributes */
    public function create(array $attributes, int $actorId): Announcement
    {
        $management = $this->systemIdentities->management();
        $payload = $this->normalized($attributes);
        $payload['created_by'] = (int) $management->id;

        return DB::transaction(function () use ($payload, $management): Announcement {
            $announcement = Announcement::query()->create($payload);
            if ((bool) $announcement->should_pin) {
                $this->syncPins($announcement, (int) $management->id);
            }
            return $announcement->refresh();
        });
    }

    /** @param array<string,mixed> $attributes */
    public function update(Announcement $announcement, array $attributes, int $actorId): Announcement
    {
        $management = $this->systemIdentities->management();
        $payload = $this->normalized($attributes, $announcement);
        $payload['created_by'] = (int) $management->id;

        return DB::transaction(function () use ($announcement, $payload, $management): Announcement {
            $announcement->fill($payload)->save();
            $this->removePinsAndGeneratedMessages($announcement);
            if ((bool) $announcement->should_pin) {
                $this->syncPins($announcement, (int) $management->id);
            }
            return $announcement->refresh();
        });
    }

    public function unpin(Announcement $announcement): Announcement
    {
        return DB::transaction(function () use ($announcement): Announcement {
            $this->removePinsAndGeneratedMessages($announcement);
            $announcement->forceFill(['should_pin' => false])->save();
            return $announcement->refresh();
        });
    }

    public function delete(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $this->removePinsAndGeneratedMessages($announcement);
            $announcement->delete();
        });
    }

    protected function syncPins(Announcement $announcement, int $systemIdentityId): void
    {
        Group::query()
            ->where('location_level', $announcement->group_level)
            ->orderBy('id')
            ->chunkById(200, function ($groups) use ($announcement, $systemIdentityId): void {
                foreach ($groups as $group) {
                    PinnedMessage::query()->updateOrCreate([
                        'group_id' => $group->id,
                        'content_type' => Announcement::class,
                        'content_id' => $announcement->id,
                    ], [
                        'message_id' => null,
                        'pinned_by' => $systemIdentityId,
                        'announcement_id' => $announcement->id,
                    ]);
                }
            });
    }

    /**
     * Removes both new direct announcement pins and legacy synthetic chat
     * messages that older announcement publishing generated solely for pinning.
     */
    protected function removePinsAndGeneratedMessages(Announcement $announcement): void
    {
        $pins = PinnedMessage::query()
            ->where(function ($query) use ($announcement): void {
                $query->where('announcement_id', $announcement->id)
                    ->orWhere(function ($contentQuery) use ($announcement): void {
                        $contentQuery->where('content_type', Announcement::class)
                            ->where('content_id', $announcement->id);
                    });
            })
            ->get(['id', 'message_id']);

        $legacyMessageIds = $pins->pluck('message_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($pins->isNotEmpty()) {
            PinnedMessage::query()->whereIn('id', $pins->pluck('id')->all())->delete();
        }
        if ($legacyMessageIds !== []) {
            Message::query()->whereIn('id', $legacyMessageIds)->delete();
        }
    }

    /** @param array<string,mixed> $attributes @return array<string,mixed> */
    protected function normalized(array $attributes, ?Announcement $current = null): array
    {
        return [
            'title' => trim((string) ($attributes['title'] ?? $current?->title ?? '')),
            'content' => trim((string) ($attributes['content'] ?? $current?->content ?? '')),
            'group_level' => (string) ($attributes['group_level'] ?? $current?->group_level ?? ''),
            'image' => $attributes['image'] ?? $current?->image,
            'should_pin' => (bool) ($attributes['should_pin'] ?? $current?->should_pin ?? false),
        ];
    }
}
