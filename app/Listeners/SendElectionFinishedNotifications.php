<?php

namespace App\Listeners;

use App\Events\ElectionFinished;
use App\Models\Vote;
use App\Services\NotificationService;

class SendElectionFinishedNotifications
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function handle(ElectionFinished $event): void
    {
        $group = $event->group;
        $election = $event->election;
        $electedCandidates = $event->electedCandidates;

        // ارسال اعلان به منتخبین برای قبول مسئولیت
        foreach ($electedCandidates as $candidate) {
            $user = $candidate->user;
            // بررسی position از طریق votes
            $inspectorVotes = Vote::where('election_id', $election->id)
                ->where('candidate_id', $candidate->user_id)
                ->where('position', 0)
                ->count();
            $position = $inspectorVotes > 0 ? 'بازرس' : 'مدیر';
            
            $title = 'شما در انتخابات گروه ' . ($group->name ?? '') . ' منتخب شدید';
            $preview = "شما به عنوان {$position} در انتخابات گروه {$group->name} انتخاب شدید. لطفاً مسئولیت خود را قبول یا رد کنید.";
            
            $url = route('profile.index');
            $context = [
                'group_id' => $group->id,
                'election_id' => $election->id,
                'candidate_id' => $candidate->id,
                'position' => $position,
            ];

            $this->notifications->notifyUser(
                $user->id,
                $title,
                $preview,
                $url,
                'group.election.elected',
                $context
            );
        }

        // ارسال اعلان به همه اعضای گروه که انتخابات تمام شد
        $groupUsers = $group->users()->where('status', 1)->pluck('user_id')->all();
        
        if (!empty($groupUsers)) {
            $title = 'انتخابات گروه ' . ($group->name ?? '') . ' به پایان رسید';
            $preview = "انتخابات گروه {$group->name} به پایان رسید و منتخبین مشخص شدند.";
            
            $url = route('groups.chat', $group->id);
            $context = [
                'group_id' => $group->id,
                'election_id' => $election->id,
            ];

            $this->notifications->notifyMany(
                $groupUsers,
                $title,
                $preview,
                $url,
                'group.election.finished',
                $context
            );
        }
    }
}

