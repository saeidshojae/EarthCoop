<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupFeedItem;
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
        foreach ($page['events'] as &$event) {
            foreach (['message', 'content'] as $field) {
                if (isset($event['payload'][$field]) && is_string($event['payload'][$field])) {
                    $event['payload'][$field] = html_entity_decode(strip_tags(preg_replace('/<br\\s*\\/?\\s*>/i', "\n", $event['payload'][$field])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
