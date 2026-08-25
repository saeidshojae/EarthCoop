<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NajmHoda\FounderOps\FounderMinistryChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FounderMinistryChatController extends Controller
{
    public function __invoke(Request $request, FounderMinistryChatService $service)
    {
        $validated = $request->validate([
            'intent' => ['required', 'string', Rule::in(FounderMinistryChatService::INTENTS)],
            'hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        return response()->json(
            $service->respond(
                (string) $validated['intent'],
                (int) ($validated['hours'] ?? 24)
            )
        );
    }
}
