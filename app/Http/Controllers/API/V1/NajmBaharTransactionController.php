<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmBaharTransactionResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Http\Support\Api\V1\Pagination;
use App\Http\Support\Api\V1\QueryOptions;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharLedgerQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmBaharTransactionController extends Controller
{
    public function __construct(
        private readonly NajmBaharLedgerQueryService $ledger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $page = Pagination::cursor($request, 20, 100);
        $filters = QueryOptions::filters($request, [
            'type' => 'type',
            'status' => 'status',
            'account_id' => 'account_id',
        ]);

        try {
            $result = $this->ledger->history($user, $page, $filters);
        } catch (ModelNotFoundException) {
            return ApiResponse::error(
                'not_found',
                'Najm Bahar account not found.',
                404,
                null,
                false,
            );
        }

        $items = collect($result['items'])
            ->map(fn (array $item) => (new NajmBaharTransactionResource($item))->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success($items, 200, [
            'pagination' => $result['pagination'],
        ]);
    }
}
