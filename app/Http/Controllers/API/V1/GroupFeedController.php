<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\GroupTextProjection;
use App\Models\Group;
use App\Models\GroupFeedItem;
use App\Models\Message;
use App\Services\Group\Api\CanonicalGroupQueryService;
use App\Services\GroupChat\GroupFeedDeltaService;
use App\Services\GroupChat\GroupFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GroupFeedController extends Controller
{
    public function delta(
        Request $request,
        Group $group,
        CanonicalGroupQueryService $groups,
        GroupFeedDeltaService $delta,
    ): JsonResponse {
        $groups->findFor($request->user(), $group);
        $validated = $request->validate([
            'after_sequence' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'window' => ['nullable', 'in:latest'],
        ]);

        $limit = (int) ($validated['limit'] ?? 100);
        $after = (int) ($validated['after_sequence'] ?? 0);
        if (($validated['window'] ?? null) === 'latest') {
            // Select actual rows: missing/deleted sequence numbers must not shorten the window.
            $sequences = GroupFeedItem::where('group_id', $group->id)
                ->orderByDesc('sequence')->limit($limit)->pluck('sequence');
            $after = max(0, (int) ($sequences->min() ?? 1) - 1);
        }
        $page = $delta->forGroup($group, $after, $limit);
        $fileIds = collect($page['events'])->filter(fn (array $event): bool => ($event['payload']['content_type'] ?? null) === 'file')
            ->pluck('payload.content_id')->filter()->values();
        $files = $fileIds->isEmpty() ? collect() : Message::query()->where('group_id', $group->id)
            ->whereIn('id', $fileIds)->get()->keyBy('id');
        foreach ($page['events'] as &$event) {
            $file = ($event['payload']['content_type'] ?? null) === 'file' ? $files->get($event['payload']['content_id'] ?? null) : null;
            if ($file && $file->file_path && $file->lifecycle_state !== 'deleted' && $file->deleted_at === null) {
                $event['payload']['attachment'] = [
                    'file_name' => $file->file_name,
                    'mime_type' => $file->file_type ?: 'application/octet-stream',
                    'download_path' => '/groups/'.$group->id.'/messages/'.$file->id.'/attachment',
                ];
            }

            foreach (['message', 'content'] as $field) {
                if (isset($event['payload'][$field]) && is_string($event['payload'][$field])) {
                    $event['payload'][$field] = GroupTextProjection::fromHtml($event['payload'][$field]);
                }
            }
        }
        unset($event);
        return response()->json($page);
    }

    public function unread(
        Request $request,
        Group $group,
        CanonicalGroupQueryService $groups,
        GroupFeedService $feed,
    ): JsonResponse {
        $groups->findFor($request->user(), $group);
        abort_unless($feed->available(), 409, 'Feed cursor is not available yet.');

        return response()->json($feed->unreadCounts((int) $group->id, (int) $request->user()->id));
    }

    public function read(
        Request $request,
        Group $group,
        CanonicalGroupQueryService $groups,
        GroupFeedService $feed,
    ): JsonResponse {
        $groups->findFor($request->user(), $group);
        abort_unless($feed->available(), 409, 'Feed cursor is not available yet.');
        $validated = $request->validate([
            'through_sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $cursor = $feed->markRead(
            (int) $group->id,
            (int) $request->user()->id,
            array_key_exists('through_sequence', $validated) ? (int) $validated['through_sequence'] : null,
        );

        return response()->json([
            'cursor' => $cursor,
            'unread' => $feed->unreadCounts((int) $group->id, (int) $request->user()->id),
        ]);
    }
}
