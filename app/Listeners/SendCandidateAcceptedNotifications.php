<?php

namespace App\Listeners;

use App\Events\CandidateAccepted;
use App\Models\Vote;
use App\Services\NotificationService;

class SendCandidateAcceptedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(CandidateAccepted $event): void
    {
        $group = $event->group;
        $candidate = $event->candidate;
        $user = $event->user;
        $election = $event->election;

        // ارسال اعلان به همه اعضای گروه
        $groupUsers = $group->users()->where('status', 1)->pluck('user_id')->all();
        
        if (!empty($groupUsers)) {
            // بررسی position از طریق votes
            $inspectorVotes = Vote::where('election_id', $election->id)
                ->where('candidate_id', $candidate->user_id)
                ->where('position', 0)
                ->count();
            $position = $inspectorVotes > 0 ? 'بازرس' : 'مدیر';
            
            $title = $user->fullName() . ' مسئولیت ' . $position . ' را در گروه ' . ($group->name ?? '') . ' پذیرفت';
            $preview = "{$user->fullName()} به عنوان {$position} در گروه {$group->name} مسئولیت خود را پذیرفت.";
            
            $url = route('groups.chat', $group->id);
            $context = [
                'group_id' => $group->id,
                'election_id' => $election->id,
                'candidate_id' => $candidate->id,
                'user_id' => $user->id,
                'position' => $position,
            ];

            $this->notifications->notifyMany(
                $groupUsers,
                $title,
                $preview,
                $url,
                'group.election.accepted',
                $context
            );
        }
    }
}

