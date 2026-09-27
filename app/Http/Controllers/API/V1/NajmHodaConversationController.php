<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\NajmHodaConversationResource;
use App\Http\Support\Api\V1\ApiResponse;
use App\Services\NajmHoda\Api\NajmHodaConversationService;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NajmHodaConversationController extends Controller
{
    public function __construct(
        private readonly NajmHodaConversationService $conversations,
        private readonly NajmHodaExecutionService $execution,
        private readonly NajmHodaOrchestrator $orchestrator,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:active,archived,deleted',
            'agent' => 'nullable|in:auto,engineer,pilot,steward,guide',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $paginator = $this->conversations->list(
            $request->user(),
            [
                'status' => $validated['status'] ?? null,
                'agent' => $validated['agent'] ?? null,
            ],
            (int) ($validated['per_page'] ?? 20),
        );

        $data = collect($paginator->items())
            ->map(fn ($conversation) => (new NajmHodaConversationResource($conversation))->resolve($request))
            ->values()
            ->all();

        return ApiResponse::success($data, 200, [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_type' => 'nullable|in:auto,engineer,pilot,steward,guide',
            'title' => 'nullable|string|max:200',
        ]);

        $conversation = $this->conversations->startOrGet(
            $request->user(),
            null,
            $validated['agent_type'] ?? 'auto',
            $validated['title'] ?? null,
        );

        return ApiResponse::success(
            (new NajmHodaConversationResource($conversation))->resolve($request),
            201,
        );
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $model = $this->conversations->get($request->user(), $conversation);
        $model->load(['messages' => fn ($query) => $query->orderBy('created_at')]);

        return ApiResponse::success(
            (new NajmHodaConversationResource($model))->resolve($request),
        );
    }

    public function message(Request $request, int $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'agent' => 'nullable|in:auto,engineer,pilot,steward,guide',
            'context' => 'nullable|array',
            'context.page' => 'nullable|array',
        ]);

        $actor = $request->user();
        $model = $this->conversations->get($actor, $conversation);
        $this->conversations->appendUserMessage($model, (string) $validated['message']);

        $context = [
            'conversation' => $model,
            'user_id' => (int) $actor->id,
            'user_is_admin' => (bool) ($actor->is_admin || $actor->hasRole('super-admin')),
        ];

        $page = data_get($validated, 'context.page');
        if (is_array($page)) {
            $context['page'] = $page;
        }

        if (! empty($validated['agent']) && $validated['agent'] !== 'auto') {
            $context['force_agent'] = (string) $validated['agent'];
        }

        $result = $this->execution->executeChat(
            $this->orchestrator,
            (string) $validated['message'],
            $context,
        );

        if (! (bool) ($result['success'] ?? false)) {
            return ApiResponse::error(
                'hoda_execution_failed',
                (string) ($result['message'] ?? 'Najm Hoda could not complete the request.'),
                502,
                $result['error'] ?? null,
                true,
            );
        }

        $this->conversations->appendAssistantMessage(
            $model,
            (string) ($result['message'] ?? ''),
            (string) ($result['agent'] ?? 'unknown'),
        );

        return ApiResponse::success([
            'conversation_id' => (int) $model->id,
            'message' => (string) ($result['message'] ?? ''),
            'agent' => (string) ($result['agent'] ?? 'unknown'),
            'agent_name' => (string) ($result['agent_name'] ?? 'نجم هدا'),
            'agent_icon' => (string) ($result['agent_icon'] ?? '🤖'),
            'suggestions' => (array) ($result['suggestions'] ?? []),
            'response_time_ms' => (int) ($result['response_time_ms'] ?? 0),
        ]);
    }
}
