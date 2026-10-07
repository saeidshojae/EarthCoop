<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmBaharTransactionResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Http\Support\Api\V1\Pagination;
use App\Http\Support\Api\V1\QueryOptions;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharAccountQueryService;
use App\Modules\NajmBahar\Services\Api\NajmBaharLedgerQueryService;
use App\Modules\NajmBahar\Services\Api\NajmBaharTransferApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharTransferCapabilityService;
use App\Modules\NajmBahar\Services\Api\NajmBaharTransferDestinationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharTransferException;
use App\Modules\NajmBahar\Services\Api\NajmBaharTransferReconciliationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NajmBaharTransactionController extends Controller
{
    public function __construct(
        private readonly NajmBaharLedgerQueryService $ledger,
        private readonly NajmBaharTransferApplicationService $transfers,
        private readonly NajmBaharAccountQueryService $accounts,
        private readonly NajmBaharTransferCapabilityService $transferCapability,
        private readonly NajmBaharTransferDestinationService $transferDestinations,
        private readonly NajmBaharTransferReconciliationService $transferReconciliation,
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
            return $this->notFound();
        }

        $items = collect($result['items'])
            ->map(fn (array $item) => (new NajmBaharTransactionResource($item))->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success($items, 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function transferCapability(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        return ApiResponse::success($this->transferCapability->forUser($user));
    }

    public function transferDestination(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:128'],
        ]);

        try {
            return ApiResponse::success(
                $this->transferDestinations->preview($user, (string) $validated['account_number']),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (NajmBaharTransferException $exception) {
            return ApiResponse::error(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->httpStatus,
                null,
                false,
            );
        }
    }

    public function transferByIdempotency(Request $request, string $key): JsonResponse
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
            $transaction = $this->transferReconciliation->findCompleted($user, $key);

            return ApiResponse::success([
                'transaction' => (new NajmBaharTransactionResource($transaction))->resolve($request),
            ]);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    public function storeTransfer(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedTransferFields($request);
        $this->validateExpectedTransfer($request);

        $validated = $request->validate([
            'source_account_id' => ['required', 'integer', 'min:1'],
            'destination_account_number' => ['required', 'string', 'max:128'],
            'amount_gol' => ['required', 'integer', 'min:1'],
            'balance_bucket' => ['required', 'in:active,dim'],
            'description' => ['nullable', 'string', 'max:500'],
            'expected' => ['sometimes', 'required', 'array'],
        ]);

        try {
            $result = $this->transfers->transfer(
                $user,
                (int) $validated['source_account_id'],
                trim((string) $validated['destination_account_number']),
                (int) $validated['amount_gol'],
                (string) $validated['balance_bucket'],
                trim((string) $request->header('Idempotency-Key')),
                array_key_exists('description', $validated)
                    ? ($validated['description'] === null ? null : (string) $validated['description'])
                    : null,
                $validated['expected'] ?? null,
            );

            $transaction = $this->ledger->transactionFor($user, $result['transaction']);

            return ApiResponse::success([
                'transaction' => (new NajmBaharTransactionResource($transaction))->resolve($request),
                'source_balance' => $this->accounts->balance($result['source']),
            ], 201);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        } catch (NajmBaharTransferException $exception) {
            return ApiResponse::error(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->httpStatus,
                null,
                false,
            );
        } catch (\RuntimeException $exception) {
            $message = mb_strtolower($exception->getMessage());
            $insufficient = str_contains($message, 'insufficient')
                || str_contains($message, 'موجودی کافی');

            return ApiResponse::error(
                $insufficient ? 'insufficient_available_funds' : 'transfer_not_allowed',
                $insufficient
                    ? 'Available Active balance is insufficient for this transfer.'
                    : 'This transfer is not allowed by the current Najm Bahar policy.',
                409,
                null,
                false,
            );
        }
    }

    private function validateExpectedTransfer(Request $request): void
    {
        if (! array_key_exists('expected', $request->all())) {
            return;
        }

        $expected = $request->input('expected');
        $keys = [
            'transfer_contract_version',
            'source_account_number',
            'source_active_available_gol',
            'destination_token',
        ];

        $valid = is_array($expected)
            && count($expected) === count($keys)
            && array_diff($keys, array_keys($expected)) === []
            && ($expected['transfer_contract_version'] ?? null) === 1
            && is_string($expected['source_account_number'] ?? null)
            && trim((string) $expected['source_account_number']) !== ''
            && is_int($expected['source_active_available_gol'] ?? null)
            && $expected['source_active_available_gol'] >= 0
            && is_string($expected['destination_token'] ?? null)
            && trim((string) $expected['destination_token']) !== '';

        if (! $valid) {
            throw ValidationException::withMessages([
                'expected' => ['A complete exact native transfer consent snapshot is required.'],
            ]);
        }
    }

    private function rejectUnexpectedTransferFields(Request $request): void
    {
        $allowed = [
            'source_account_id',
            'destination_account_number',
            'amount_gol',
            'balance_bucket',
            'description',
            'expected',
        ];

        $unexpected = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unsupported transfer fields: '.implode(', ', $unexpected)],
            ]);
        }
    }

    private function notFound(): JsonResponse
    {
        return ApiResponse::error(
            'not_found',
            'Najm Bahar account or transaction not found.',
            404,
            null,
            false,
        );
    }
}
