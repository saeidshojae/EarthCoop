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

    public function storeTransfer(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedTransferFields($request);

        $validated = $request->validate([
            'source_account_id' => ['required', 'integer', 'min:1'],
            'destination_account_number' => ['required', 'string', 'max:128'],
            'amount_gol' => ['required', 'integer', 'min:1'],
            'balance_bucket' => ['required', 'in:active,dim'],
            'description' => ['nullable', 'string', 'max:500'],
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
            );

            $transaction = $this->ledger->transactionFor($user, $result['transaction']);

            return ApiResponse::success([
                'transaction' => (new NajmBaharTransactionResource($transaction))->resolve($request),
                'source_balance' => $this->accounts->balance($result['source']),
            ], 201);
        } catch (ModelNotFoundException) {
            return $this->notFound();
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

    private function rejectUnexpectedTransferFields(Request $request): void
    {
        $allowed = [
            'source_account_id',
            'destination_account_number',
            'amount_gol',
            'balance_bucket',
            'description',
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
