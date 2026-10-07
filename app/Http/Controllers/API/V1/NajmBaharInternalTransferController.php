<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmBaharTransactionResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharInternalTransferApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharInternalTransferException;
use App\Modules\NajmBahar\Services\Api\NajmBaharLedgerQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NajmBaharInternalTransferController extends Controller
{
    public function __construct(
        private readonly NajmBaharInternalTransferApplicationService $transfers,
        private readonly NajmBaharLedgerQueryService $ledger,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedFields($request);

        $validated = $request->validate([
            'direction' => ['required', 'in:main_to_sub,sub_to_main,sub_to_sub'],
            'source_sub_account_id' => ['nullable', 'integer', 'min:1'],
            'destination_sub_account_id' => ['nullable', 'integer', 'min:1'],
            'amount_gol' => ['required', 'integer', 'min:1'],
            'balance_bucket' => ['required', 'in:active,dim'],
            'description' => ['nullable', 'string', 'max:500'],
            'expected' => ['required', 'array'],
            'expected.internal_transfer_contract_version' => ['required', 'integer', 'in:1'],
            'expected.source_account_number' => ['required', 'string', 'max:128'],
            'expected.source_available_gol' => ['required', 'integer', 'min:0'],
            'expected.destination_account_number' => ['required', 'string', 'max:128'],
        ]);

        try {
            $result = $this->transfers->transfer(
                $user,
                (string) $validated['direction'],
                isset($validated['source_sub_account_id'])
                    ? (int) $validated['source_sub_account_id']
                    : null,
                isset($validated['destination_sub_account_id'])
                    ? (int) $validated['destination_sub_account_id']
                    : null,
                (int) $validated['amount_gol'],
                (string) $validated['balance_bucket'],
                trim((string) $request->header('Idempotency-Key')),
                array_key_exists('description', $validated)
                    ? ($validated['description'] === null ? null : (string) $validated['description'])
                    : null,
                (array) $validated['expected'],
            );

            $transaction = $this->ledger->transactionFor($user, $result['transaction']);

            return ApiResponse::success([
                'transaction' => (new NajmBaharTransactionResource($transaction))->resolve($request),
                'source' => $result['source'],
                'destination' => $result['destination'],
            ], 201);
        } catch (ModelNotFoundException) {
            return ApiResponse::error(
                'not_found',
                'Najm Bahar account or sub-account not found.',
                404,
                null,
                false,
            );
        } catch (NajmBaharInternalTransferException $exception) {
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
                $insufficient ? 'insufficient_available_funds' : 'internal_transfer_not_allowed',
                $insufficient
                    ? 'Available balance is insufficient for this internal transfer.'
                    : 'This internal transfer is not allowed.',
                409,
                null,
                false,
            );
        }
    }

    private function rejectUnexpectedFields(Request $request): void
    {
        $allowed = [
            'direction',
            'source_sub_account_id',
            'destination_sub_account_id',
            'amount_gol',
            'balance_bucket',
            'description',
            'expected',
        ];

        $unexpected = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unsupported internal transfer fields: '.implode(', ', $unexpected)],
            ]);
        }

        $expected = $request->input('expected');
        if (is_array($expected)) {
            $expectedAllowed = [
                'internal_transfer_contract_version',
                'source_account_number',
                'source_available_gol',
                'destination_account_number',
            ];
            $unexpectedExpected = array_values(array_diff(array_keys($expected), $expectedAllowed));
            if ($unexpectedExpected !== []) {
                throw ValidationException::withMessages([
                    'expected' => ['Unsupported expected fields: '.implode(', ', $unexpectedExpected)],
                ]);
            }
        }
    }
}
