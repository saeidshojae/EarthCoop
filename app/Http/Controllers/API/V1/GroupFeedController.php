<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
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
        ]);

        return response()->json($delta->forGroup(
            $group,
            (int) ($validated['after_sequence'] ?? 0),
            (int) ($validated['limit'] ?? 100),
        ));
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
