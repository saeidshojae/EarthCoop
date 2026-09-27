<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmHodaActionResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Services\NajmHoda\Api\NajmHodaActionApplicationService;
use App\Services\NajmHoda\Api\NajmHodaEvidenceService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class NajmHodaActionController extends Controller
{
    public function __construct(
        private readonly NajmHodaActionApplicationService $actions,
        private readonly NajmHodaEvidenceService $evidence,
    ) {
    }

    public function propose(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'nullable|integer|min:1',
            'action' => 'required|string|max:120',
            'input' => 'required|array',
        ]);

        try {
            $record = $this->actions->propose(
                $request->user(),
                (string) $validated['action'],
                (array) $validated['input'],
                isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error(
                'capability_rejected',
                'Action capability validation failed.',
                422,
                ['reason' => $exception->getMessage()],
                false,
            );
        }

        return ApiResponse::success(
            (new NajmHodaActionResource($record))->resolve($request),
            201,
        );
    }

    public function consent(Request $request, string $action): JsonResponse
    {
        try {
            $record = $this->actions->consent($request->user(), $action);
        } catch (DomainException $exception) {
            return ApiResponse::error(
                (string) $exception->getMessage(),
                'Action cannot be consented in its current state.',
                409,
                null,
                false,
            );
        }

        return ApiResponse::success(
            (new NajmHodaActionResource($record))->resolve($request),
        );
    }

    public function apply(Request $request, string $action): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        try {
            $record = $this->actions->apply($request->user(), $action, $idempotencyKey);
        } catch (DomainException $exception) {
            return ApiResponse::error(
                (string) $exception->getMessage(),
                'Explicit consent is required before apply.',
                409,
                null,
                false,
            );
        } catch (AuthorizationException $exception) {
            $reason = trim($exception->getMessage()) ?: 'resource_not_accessible';

            return ApiResponse::error(
                $reason,
                'The requested resource is not accessible.',
                403,
                null,
                false,
            );
        } catch (InvalidArgumentException $exception) {
            $reason = trim($exception->getMessage()) ?: 'action_not_allowed';

            return ApiResponse::error(
                $reason,
                'The action is not allowed.',
                422,
                null,
                false,
            );
        }

        return ApiResponse::success(
            (new NajmHodaActionResource($record))->resolve($request),
        );
    }

    public function evidence(Request $request, string $action): JsonResponse
    {
        return ApiResponse::success(
            $this->evidence->get($request->user(), $action),
        );
    }
}
