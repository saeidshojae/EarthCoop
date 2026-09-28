<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmBaharTransactionResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharAccountQueryService;
use App\Modules\NajmBahar\Services\Api\NajmBaharActivationApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharActivationException;
use App\Modules\NajmBahar\Services\Api\NajmBaharLedgerQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NajmBaharActivationController extends Controller
{
    public function __construct(
        private readonly NajmBaharActivationApplicationService $activation,
        private readonly NajmBaharLedgerQueryService $ledger,
        private readonly NajmBaharAccountQueryService $accounts,
    ) {
    }

    public function eligibility(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        try {
            return ApiResponse::success($this->activation->eligibility($user));
        } catch (NajmBaharActivationException $exception) {
            return $this->activationError($exception);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedFields($request);

        $validated = $request->validate([
            'source' => ['required', 'in:participation'],
            'points' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->activation->activate(
                $user,
                (int) $validated['points'],
                trim((string) $request->header('Idempotency-Key')),
            );

            $transaction = $this->ledger->transactionFor($user, $result['transaction']);

            return ApiResponse::success([
                'source' => 'participation',
                'requested_points' => (int) $result['requested_points'],
                'consumed_points' => (int) $result['consumed_points'],
                'activated_gol' => (int) $result['activated_gol'],
                'transaction' => (new NajmBaharTransactionResource($transaction))->resolve($request),
                'balance' => $this->accounts->balance($result['account']),
            ], 201);
        } catch (NajmBaharActivationException $exception) {
            return $this->activationError($exception);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    private function rejectUnexpectedFields(Request $request): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), ['source', 'points']));
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unsupported activation fields: '.implode(', ', $unexpected)],
            ]);
        }
    }

    private function activationError(NajmBaharActivationException $exception): JsonResponse
    {
        return ApiResponse::error(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->httpStatus,
            null,
            false,
        );
    }

    private function notFound(): JsonResponse
    {
        return ApiResponse::error(
            'not_found',
            'Najm Bahar account not found.',
            404,
            null,
            false,
        );
    }
}
