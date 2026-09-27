<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json((new UserResource($request->user()))->resolve($request));
    }
}
