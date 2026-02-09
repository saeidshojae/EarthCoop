<?php

namespace App\Services;

use App\Models\SupportChat;
use App\Models\User;

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
        // پیدا کردن پشتیبان‌های آنلاین که چت‌های کمتری دارند
        $agents = User::where('is_admin', 1)
            ->orWhereHas('roles', function($query) {
                $query->where('name', 'support_agent');
            })
            ->get();

        if ($agents->isEmpty()) {
            return null;
        }

        // شمارش چت‌های فعال هر پشتیبان
        $agentLoads = [];
        foreach ($agents as $agent) {
            $activeChats = SupportChat::where('agent_id', $agent->id)
                ->whereIn('status', ['waiting', 'active'])
                ->count();
            
            $agentLoads[$agent->id] = $activeChats;
        }

        // انتخاب پشتیبانی با کمترین بار
        $selectedAgentId = array_search(min($agentLoads), $agentLoads);
        $selectedAgent = $agents->firstWhere('id', $selectedAgentId);

        if ($selectedAgent) {
            $chat->update([
                'agent_id' => $selectedAgent->id,
                'status' => 'active',
                'last_activity_at' => now(),
            ]);
        }

        return $selectedAgent;
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
        if (!$this->isSupportAgent($agent)) {
            return false;
        }

        $chat->update([
            'agent_id' => $agent->id,
            'status' => 'active',
            'last_activity_at' => now(),
        ]);

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
        $waitingChats = $this->getWaitingChats();
        $assigned = 0;

        foreach ($waitingChats as $chat) {
            if ($this->assignToAvailableAgent($chat)) {
                $assigned++;
            }
        }

        return $assigned;
    }
}




