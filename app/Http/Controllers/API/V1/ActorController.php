<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\Actors\ActorDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActorController extends Controller
{
    public function index(Request $request, ActorDiscoveryService $actors): JsonResponse
    {
        return response()->json($actors->for($request->user()));
    }
}
