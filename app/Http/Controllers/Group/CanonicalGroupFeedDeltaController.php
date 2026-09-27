<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupChat\GroupFeedDeltaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CanonicalGroupFeedDeltaController extends Controller
{
    public function __invoke(Group $group, Request $request, GroupFeedDeltaService $delta): JsonResponse
    {
        $this->authorize('view', $group);
        $validated = $request->validate([
            'after_sequence' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json(array_merge([
            'status' => 'success',
        ], $delta->forGroup(
            $group,
            (int) ($validated['after_sequence'] ?? 0),
            (int) ($validated['limit'] ?? 100),
        )));
    }
}
