<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Invitation;
use App\Services\Invitation\InvitationManagementService;

class FounderInvitationCandidateService
{
    public function __construct(protected InvitationManagementService $invitations) {}

    public function candidates(int $limit=20): array
    {
        $limit=max(1,min($limit,100));
        return Invitation::query()->where('status',0)->oldest('created_at')->limit($limit)->get()
            ->map(function(Invitation $invitation): array {
                $recommendation=$this->invitations->recommend($invitation);
                return [
                    'invitation_id'=>(int)$invitation->id,
                    'email'=>(string)$invitation->email,
                    'job'=>(string)($invitation->job??''),
                    'created_at'=>optional($invitation->created_at)->toIso8601String(),
                    'recommendation'=>(string)($recommendation['recommendation']??'manual_review'),
                    'signals'=>(array)($recommendation['signals']??[]),
                ];
            })->all();
    }
}
