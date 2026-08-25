<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\FounderOps\FounderMinistryAgencyService;
use App\Services\NajmHoda\FounderOps\FounderMinistryChatService;
use App\Services\NajmHoda\FounderOps\FounderMinistryExecutivePresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FounderMinistryChatController extends Controller
{
    public const UAT_VERSION = 'founder-ministry-v4-2026-08-26';

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
            'action_cards' => true,
            'executive_briefs' => true,
            'exception_driven_morning_brief' => true,
            'executive_agency' => true,
            'agency_authority_source' => 'server_side_founder_ops_evidence',
            'execution_boundary' => 'existing_founder_ops_approval_authority_lifecycle',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function __invoke(
        Request $request,
        FounderMinistryChatService $service,
        FounderMinistryExecutivePresenter $presenter,
        FounderMinistryAgencyService $agency,
    ) {
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

        $hours = (int) ($validated['hours'] ?? 24);
        $response = $service->respond($intent, $hours);
        $agencySnapshot = $agency->describe(
            $intent,
            (array) data_get($response, 'management.items', [])
        );

        return response()->json($presenter->present($response, $hours, $agencySnapshot));
    }
}
