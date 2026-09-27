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

        $validated = $request->validate([
            'payment_source' => ['required', 'in:active,dim'],
            'sub_account_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return ApiResponse::success(
                $this->membershipFee->pay(
                    $user,
                    (string) $validated['payment_source'],
                    isset($validated['sub_account_id']) ? (int) $validated['sub_account_id'] : null,
                ),
                201,
            );
        } catch (NajmBaharMembershipFeeException $exception) {
            return $this->domainError($exception);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    private function rejectUnexpectedFields(Request $request): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), [
            'payment_source',
            'sub_account_id',
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
