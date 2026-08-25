<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\FounderOps\FounderMinistryChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FounderMinistryChatController extends Controller
{
    public const UAT_VERSION = 'founder-ministry-v1-2026-08-25';

    public function readiness()
    {
        return response()->json([
            'success' => true,
            'feature' => 'founder_ministry',
            'version' => self::UAT_VERSION,
            'mode' => 'read_only_decision_support',
            'read_only_intents' => FounderMinistryChatService::INTENTS,
            'typed_execution_inference' => false,
            'approval_bypass' => false,
            'execution_boundary' => 'existing_founder_ops_approval_authority_lifecycle',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function __invoke(Request $request, FounderMinistryChatService $service)
    {
        $validated = $request->validate([
            'intent' => ['nullable', 'required_without:message', 'string', Rule::in(FounderMinistryChatService::INTENTS)],
            'message' => ['nullable', 'required_without:intent', 'string', 'max:5000'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $intent = isset($validated['intent'])
            ? (string) $validated['intent']
            : $service->inferIntent((string) ($validated['message'] ?? ''));

        if ($intent === null) {
            return response()->json($service->unclassifiedResponse(), 422);
        }

        return response()->json(
            $service->respond(
                $intent,
                (int) ($validated['hours'] ?? 24)
            )
        );
    }
}
