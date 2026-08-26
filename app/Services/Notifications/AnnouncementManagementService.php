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

    /**
     * Convert legacy announcement pins that were represented by synthetic chat
     * messages into direct Announcement pins and attribute all announcements to
     * the canonical EarthCoop management identity.
     *
     * @return array{announcements_reattributed:int,legacy_pins_repaired:int,legacy_messages_deleted:int,pins_created:int}
     */
    public function repairLegacyArtifacts(): array
    {
        $management = $this->systemIdentities->management();
        $stats = [
            'announcements_reattributed' => 0,
            'legacy_pins_repaired' => 0,
            'legacy_messages_deleted' => 0,
            'pins_created' => 0,
        ];

        DB::transaction(function () use ($management, &$stats): void {
            $stats['announcements_reattributed'] = Announcement::query()
                ->where(function ($query) use ($management): void {
                    $query->whereNull('created_by')->orWhere('created_by', '!=', (int) $management->id);
                })
                ->update(['created_by' => (int) $management->id]);

            PinnedMessage::query()
                ->whereNotNull('announcement_id')
                ->whereNotNull('message_id')
                ->orderBy('id')
                ->chunkById(200, function ($pins) use ($management, &$stats): void {
                    foreach ($pins as $pin) {
                        $announcement = Announcement::query()->find((int) $pin->announcement_id);
                        if (! $announcement) {
                            continue;
                        }

                        $legacyMessageId = (int) $pin->message_id;
                        $direct = PinnedMessage::query()
                            ->where('group_id', (int) $pin->group_id)
                            ->where('content_type', Announcement::class)
                            ->where('content_id', (int) $announcement->id)
                            ->where('id', '!=', (int) $pin->id)
                            ->first();

                        if ($direct) {
                            $pin->delete();
                        } else {
                            $pin->forceFill([
                                'message_id' => null,
                                'announcement_id' => (int) $announcement->id,
                                'content_type' => Announcement::class,
                                'content_id' => (int) $announcement->id,
                                'pinned_by' => (int) $management->id,
                            ])->save();
                        }

                        if ($legacyMessageId > 0 && Message::query()->whereKey($legacyMessageId)->delete()) {
                            $stats['legacy_messages_deleted']++;
                        }
                        $stats['legacy_pins_repaired']++;
                    }
                });

            Announcement::query()
                ->where('should_pin', true)
                ->orderBy('id')
                ->chunkById(100, function ($announcements) use ($management, &$stats): void {
                    foreach ($announcements as $announcement) {
                        Group::query()
                            ->where('location_level', $announcement->group_level)
                            ->orderBy('id')
                            ->chunkById(200, function ($groups) use ($announcement, $management, &$stats): void {
                                foreach ($groups as $group) {
                                    $pin = PinnedMessage::query()->firstOrCreate([
                                        'group_id' => $group->id,
                                        'content_type' => Announcement::class,
                                        'content_id' => $announcement->id,
                                    ], [
                                        'message_id' => null,
                                        'pinned_by' => (int) $management->id,
                                        'announcement_id' => $announcement->id,
                                    ]);
                                    if ($pin->wasRecentlyCreated) {
                                        $stats['pins_created']++;
                                    }
                                }
                            });
                    }
                });
        });

        return $stats;
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
