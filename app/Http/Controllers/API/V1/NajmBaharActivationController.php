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
use Illuminate\Support\Facades\DB;
use App\Models\UserPointConversion;

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

        $record = DB::table('api_v1_idempotency_keys')
            ->where('actor_key', 'user:'.$user->getAuthIdentifier())
            ->where('scope', 'api.v1.najm-bahar.activation.store')
            ->where('idempotency_key', $key)
            ->where('state', 'completed')
            ->where('response_status', 201)
            ->first();

        $conversion = UserPointConversion::query()
            ->where('user_id', (int) $user->id)
            ->where('request_key', $key)
            ->where('status', 'applied')
            ->first();

        if ($record === null || $conversion === null) {
            return $this->notFound();
        }

        $body = json_decode((string) $record->response_body, true);
        $data = is_array($body) ? ($body['data'] ?? null) : null;
        if (! is_array($data)
            || ($body['status'] ?? null) !== 'success'
            || ($data['source'] ?? null) !== 'participation'
            || (int) ($data['requested_points'] ?? 0) !== (int) $conversion->requested_points
            || (int) ($data['consumed_points'] ?? 0) !== (int) $conversion->consumed_points
            || (int) ($data['activated_gol'] ?? 0) !== (int) $conversion->amount_gol
            || ! is_array($data['transaction'] ?? null)
            || empty($data['transaction']['id'])) {
            return $this->notFound();
        }

        return ApiResponse::success($data);
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
            'expected' => ['sometimes', 'required', 'array:activation_contract_version,policy_version_id,policy_version,conversion_ratio_points_per_gol,remaining_convertible_points,dim_available_gol,max_activation_gol'],
            'expected.activation_contract_version' => ['required_with:expected', 'integer', 'in:1'],
            'expected.policy_version_id' => ['present_with:expected', 'nullable', 'integer', 'min:1'],
            'expected.policy_version' => ['present_with:expected', 'nullable', 'integer', 'min:1'],
            'expected.conversion_ratio_points_per_gol' => ['required_with:expected', 'integer', 'min:1'],
            'expected.remaining_convertible_points' => ['required_with:expected', 'integer', 'min:0'],
            'expected.dim_available_gol' => ['required_with:expected', 'integer', 'min:0'],
            'expected.max_activation_gol' => ['required_with:expected', 'integer', 'min:0'],
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
