<?php

namespace App\Services;

use App\Models\SupportChat;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaDomainEventPolicyLinkService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Throwable;

/**
 * سرویس توزیع خودکار چت‌های پشتیبانی به پشتیبان‌ها
 */
class SupportChatAssignmentService
{
    /**
     * اختصاص خودکار چت به پشتیبان موجود
     */
    public function assignToAvailableAgent(SupportChat $chat): ?User
    {
        $context = [
            'chat_id' => (int) $chat->id,
            'user_id' => $chat->user_id !== null ? (int) $chat->user_id : null,
            'scope' => 'support:chat',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.auto.requested', $context);

        try {
            $agents = User::where('is_admin', 1)
                ->orWhereHas('roles', function ($query) {
                    $query->where('name', 'support_agent');
                })
                ->get();

            if ($agents->isEmpty()) {
                $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.auto.rejected', array_merge($context, [
                    'reason' => 'no_support_agents',
                    'risk' => 'medium',
                ]));
                return null;
            }

            $agentLoads = [];
            foreach ($agents as $agent) {
                $activeChats = SupportChat::where('agent_id', $agent->id)
                    ->whereIn('status', ['waiting', 'active'])
                    ->count();

                $agentLoads[$agent->id] = $activeChats;
            }

            $selectedAgentId = array_search(min($agentLoads), $agentLoads, true);
            $selectedAgent = $agents->firstWhere('id', $selectedAgentId);

            if ($selectedAgent) {
                $chat->update([
                    'agent_id' => $selectedAgent->id,
                    'status' => 'active',
                    'last_activity_at' => now(),
                ]);

                $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.auto.succeeded', array_merge($context, [
                    'agent_id' => (int) $selectedAgent->id,
                    'agent_load' => (int) ($agentLoads[$selectedAgent->id] ?? 0),
                ]));
            }

            return $selectedAgent;
        } catch (Throwable $e) {
            $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.auto.failed', array_merge($context, [
                'error' => $e->getMessage(),
                'risk' => 'medium',
            ]));

            throw $e;
        }
    }

    /**
     * پیدا کردن چت‌های در انتظار
     */
    public function getWaitingChats(): \Illuminate\Database\Eloquent\Collection
    {
        return SupportChat::where('status', 'waiting')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * اختصاص دستی چت به پشتیبان
     */
    public function assignToAgent(SupportChat $chat, User $agent): bool
    {
        $context = [
            'chat_id' => (int) $chat->id,
            'user_id' => $chat->user_id !== null ? (int) $chat->user_id : null,
            'agent_id' => (int) $agent->id,
            'scope' => 'support:chat',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.manual.requested', $context);

        if (!$this->isSupportAgent($agent)) {
            $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.manual.rejected', array_merge($context, [
                'reason' => 'agent_not_eligible',
                'risk' => 'medium',
            ]));
            return false;
        }

        $chat->update([
            'agent_id' => $agent->id,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

        $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.manual.succeeded', $context);

        return true;
    }

    /**
     * بررسی اینکه آیا کاربر پشتیبان است یا نه
     */
    public function isSupportAgent(User $user): bool
    {
        return $user->is_admin == 1 || $user->hasRole('support_agent');
    }

    /**
     * توزیع خودکار چت‌های در انتظار
     */
    public function autoAssignWaitingChats(): int
    {
        $context = [
            'scope' => 'support:chat',
            'risk' => 'low',
        ];
        $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.bulk.requested', $context);

        $waitingChats = $this->getWaitingChats();
        $assigned = 0;

        foreach ($waitingChats as $chat) {
            if ($this->assignToAvailableAgent($chat)) {
                $assigned++;
            }
        }

        $this->emitRuntime('najm_hoda.input.support.service.chat_assignment.bulk.succeeded', array_merge($context, [
            'waiting_count' => (int) $waitingChats->count(),
            'assigned_count' => (int) $assigned,
        ]));

        return $assigned;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function emitRuntime(string $event, array $payload): void
    {
        try {
            /** @var RuntimeEventBus $bus */
            $bus = app(RuntimeEventBus::class);
            $bus->emit($event, $payload);

            /** @var NajmHodaDomainEventPolicyLinkService $link */
            $link = app(NajmHodaDomainEventPolicyLinkService::class);
            $link->ingest($event, $payload);
        } catch (Throwable) {
            // no-op
        }
    }
}




