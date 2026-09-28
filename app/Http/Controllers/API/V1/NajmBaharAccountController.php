<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmBaharAccountResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharAccountQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmBaharAccountController extends Controller
{
    public function __construct(
        private readonly NajmBaharAccountQueryService $accounts,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        try {
            $account = $this->accounts->mainFor($user);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return ApiResponse::success(
            (new NajmBaharAccountResource([
                'account' => $account,
                'balance' => $this->accounts->balance($account),
            ]))->resolve($request),
        );
    }

    public function balance(Request $request, string $account): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $ownedAccount = $this->accounts->ownedBy($user, (int) $account);

        if ($ownedAccount === null) {
            return $this->notFound();
        }

        return ApiResponse::success([
            'account_id' => (int) $ownedAccount->id,
            ...$this->accounts->balance($ownedAccount),
        ]);
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
