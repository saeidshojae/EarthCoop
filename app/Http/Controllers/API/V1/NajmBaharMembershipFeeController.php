<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharMembershipFeeApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharMembershipFeeException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NajmBaharMembershipFeeController extends Controller
{
    public function __construct(
        private readonly NajmBaharMembershipFeeApplicationService $membershipFee,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        try {
            return ApiResponse::success($this->membershipFee->info($user));
        } catch (NajmBaharMembershipFeeException $exception) {
            return $this->domainError($exception);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    public function pay(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedFields($request);
        $this->validateExpected($request);

        $validated = $request->validate([
            'payment_source' => ['required', 'in:active,dim'],
            'sub_account_id' => ['nullable', 'integer', 'min:1'],
            'expected' => ['sometimes', 'required', 'array'],
        ]);

        try {
            return ApiResponse::success(
                $this->membershipFee->pay(
                    $user,
                    (string) $validated['payment_source'],
                    isset($validated['sub_account_id']) ? (int) $validated['sub_account_id'] : null,
                    $validated['expected'] ?? null,
                ),
                201,
            );
        } catch (NajmBaharMembershipFeeException $exception) {
            return $this->domainError($exception);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    private function validateExpected(Request $request): void
    {
        if (! array_key_exists('expected', $request->all())) {
            return;
        }
        $expected = $request->input('expected');
        $keys = ['payment_year', 'fee_gol', 'breakdown', 'policy_version_id', 'account_number'];
        $valid = is_array($expected) && count($expected) === count($keys)
            && array_diff($keys, array_keys($expected)) === [];
        if ($valid) {
            $valid = is_int($expected['payment_year']) && $expected['payment_year'] > 0
                && is_int($expected['fee_gol']) && $expected['fee_gol'] > 0
                && ($expected['policy_version_id'] === null || (is_int($expected['policy_version_id']) && $expected['policy_version_id'] > 0))
                && is_string($expected['account_number']) && trim($expected['account_number']) !== '';
            $split = $expected['breakdown'];
            $splitKeys = ['operations_salary_gol', 'central_insurance_gol', 'money_destruction_gol'];
            $valid = $valid && is_array($split) && count($split) === 3 && array_diff($splitKeys, array_keys($split)) === [];
            if ($valid) {
                $remaining = $expected['fee_gol'];
                foreach ($splitKeys as $key) {
                    if (! is_int($split[$key]) || $split[$key] < 0 || $split[$key] > $remaining) {
                        $valid = false;
                        break;
                    }
                    $remaining -= $split[$key];
                }
                $valid = $valid && $remaining === 0;
            }
        }
        if (! $valid) {
            throw ValidationException::withMessages(['expected' => ['A complete exact integer membership consent snapshot is required.']]);
        }
    }

    private function rejectUnexpectedFields(Request $request): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), [
            'payment_source',
            'sub_account_id',
            'expected',
        ]));

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Unsupported membership-fee fields: '.implode(', ', $unexpected)],
            ]);
        }
    }

    private function domainError(NajmBaharMembershipFeeException $exception): JsonResponse
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
            'Najm Bahar membership-fee source account not found.',
            404,
            null,
            false,
        );
    }
}
