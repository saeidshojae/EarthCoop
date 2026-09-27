<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Group\Api\CanonicalGroupQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GroupController extends Controller
{
    public function index(Request $request, CanonicalGroupQueryService $groups): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '' && mb_strlen($search) < 2) {
            $search = null;
        }

        return response()->json($groups->listFor($request->user(), $search));
    }

    public function show(Request $request, Group $group, CanonicalGroupQueryService $groups): JsonResponse
    {
        return response()->json($groups->findFor($request->user(), $group));
    }
}
