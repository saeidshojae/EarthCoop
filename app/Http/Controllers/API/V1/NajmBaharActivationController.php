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

    public function byIdempotency(Request $request, string $key): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Invalid idempotency key.'],
            ]);
        }

        try {
            $result = $this->activation->reconcile($user, $key);
            $transaction = $this->ledger->transactionFor($user, $result['transaction']);

            return ApiResponse::success([
                'source' => 'participation',
                'requested_points' => (int) $result['requested_points'],
                'consumed_points' => (int) $result['consumed_points'],
                'activated_gol' => (int) $result['activated_gol'],
                'transaction' => (new NajmBaharTransactionResource($transaction))->resolve($request),
                'balance' => $this->accounts->balance($result['account']),
            ]);
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
        $this->validateExpectedActivation($request);

        $validated = $request->validate([
            'source' => ['required', 'in:participation'],
            'points' => ['required', 'integer', 'min:1'],
            'expected' => ['sometimes', 'required', 'array'],
        ]);

        try {
            $result = $this->activation->activate(
                $user,
                (int) $validated['points'],
                trim((string) $request->header('Idempotency-Key')),
                $validated['expected'] ?? null,
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

    private function validateExpectedActivation(Request $request): void
    {
        if (! array_key_exists('expected', $request->all())) {
            return;
        }

        $expected = $request->input('expected');
        $keys = [
            'activation_contract_version',
            'remaining_convertible_points',
            'conversion_ratio_points_per_gol',
            'max_convertible_points',
            'max_activation_gol',
            'dim_available_gol',
            'policy_version_id',
            'policy_version',
            'policy_source',
        ];

        $nonNegativeIntegers = [
            'remaining_convertible_points',
            'max_convertible_points',
            'max_activation_gol',
            'dim_available_gol',
        ];

        $valid = is_array($expected)
            && count($expected) === count($keys)
            && array_diff($keys, array_keys($expected)) === []
            && ($expected['activation_contract_version'] ?? null) === 1
            && is_int($expected['conversion_ratio_points_per_gol'] ?? null)
            && $expected['conversion_ratio_points_per_gol'] > 0
            && is_string($expected['policy_source'] ?? null)
            && in_array($expected['policy_source'], ['versioned_policy', 'legacy_settings'], true)
            && (
                ($expected['policy_version_id'] ?? null) === null
                || (is_int($expected['policy_version_id']) && $expected['policy_version_id'] > 0)
            )
            && (
                ($expected['policy_version'] ?? null) === null
                || is_int($expected['policy_version'])
            );

        foreach ($nonNegativeIntegers as $field) {
            $valid = $valid
                && is_int($expected[$field] ?? null)
                && $expected[$field] >= 0;
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'expected' => ['A complete exact native activation consent snapshot is required.'],
            ]);
        }
    }

    private function rejectUnexpectedFields(Request $request): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), ['source', 'points', 'expected']));
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
