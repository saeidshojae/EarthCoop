<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmHodaCapabilityResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Services\NajmHoda\Api\NajmHodaCapabilityQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmHodaCapabilityController extends Controller
{
    public function __construct(
        private readonly NajmHodaCapabilityQueryService $capabilities,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $data = collect($this->capabilities->all())
            ->map(fn (array $capability) => (new NajmHodaCapabilityResource($capability))->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success($data);
    }

    public function show(Request $request, string $action): JsonResponse
    {
        $capability = $this->capabilities->get($action);

        if ($capability === null) {
            return ApiResponse::error(
                'not_found',
                'Capability not found.',
                404,
                null,
                false,
            );
        }

        return ApiResponse::success(
            (new NajmHodaCapabilityResource($capability))->resolve($request),
        );
    }
}
