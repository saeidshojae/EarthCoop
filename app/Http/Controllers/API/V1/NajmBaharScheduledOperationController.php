<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharScheduledOperationQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmBaharScheduledOperationController extends Controller
{
    public function __construct(
        private readonly NajmBaharScheduledOperationQueryService $scheduledOperations,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        try {
            return ApiResponse::success(
                $this->scheduledOperations->forUser($user),
            );
        } catch (ModelNotFoundException) {
            return ApiResponse::error(
                'not_found',
                'Najm Bahar account not found.',
                404,
                null,
                false,
            );
        }
    }
}
