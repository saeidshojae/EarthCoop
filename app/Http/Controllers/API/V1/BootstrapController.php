<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Services\ClientCompatibility\ClientCompatibilityService;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, ClientCompatibilityService $compatibility)
    {
        $validated = $request->validate([
            'platform' => ['required', 'string'],
            'version' => ['required', 'string'],
        ]);

        $result = $compatibility->evaluate(
            (string) $validated['platform'],
            (string) $validated['version'],
        );
        $policy = $result['policy'];

        return response()->json([
            'api' => ['version' => 'v1'],
            'client' => [
                'platform' => $policy->platform,
                'minimum_version' => $policy->minimumVersion,
                'latest_version' => $policy->latestVersion,
                'update_required' => $result['update_required'],
                'update_recommended' => $result['update_recommended'],
            ],
        ]);
    }
}
