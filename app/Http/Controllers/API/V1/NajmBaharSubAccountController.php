<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\Api\V1\ApiResponse;
use App\Models\User;
use App\Modules\NajmBahar\Services\Api\NajmBaharSubAccountApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharSubAccountQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmBaharSubAccountController extends Controller
{
    public function __construct(
        private readonly NajmBaharSubAccountQueryService $query,
        private readonly NajmBaharSubAccountApplicationService $application,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        try {
            return ApiResponse::success($this->query->forUser($user));
        } catch (ModelNotFoundException) {
            return ApiResponse::error(
                'not_found',
                'Najm Bahar account not found.',
                404,
                null,
                false,
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedFields($request, ['name']);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $sub = $this->application->create(
                $user,
                array_key_exists('name', $validated) ? $validated['name'] : null,
            );

            return ApiResponse::success(
                $this->query->oneForUser($user, (int) $sub->id),
                201,
            );
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    public function update(Request $request, int $subAccount): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ApiResponse::error('unauthenticated', 'Authentication required.', 401);
        }

        $this->rejectUnexpectedFields($request, ['name']);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        if (trim((string) $validated['name']) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => ['The sub-account name must not be empty.'],
            ]);
        }

        try {
            $sub = $this->application->rename(
                $user,
                $subAccount,
                (string) $validated['name'],
            );

            return ApiResponse::success(
                $this->query->oneForUser($user, (int) $sub->id),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }
    }

    private function rejectUnexpectedFields(Request $request, array $allowed): void
    {
        $unexpected = array_values(array_diff(array_keys($request->all()), $allowed));
        if ($unexpected !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'request' => ['Unsupported sub-account fields: '.implode(', ', $unexpected)],
            ]);
        }
    }

    private function notFound(): JsonResponse
    {
        return ApiResponse::error(
            'not_found',
            'Najm Bahar sub-account not found.',
            404,
            null,
            false,
        );
    }
}
