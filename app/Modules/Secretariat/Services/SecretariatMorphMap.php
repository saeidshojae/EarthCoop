<?php

namespace App\Modules\Secretariat\Services;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\Message;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\Poll;
use App\Models\Ticket;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Database\Eloquent\Relations\Relation;

final class SecretariatMorphMap
{
    public static function register(): void
    {
        Relation::morphMap([
            'group' => Group::class,
            'najm_bahar_project' => Project::class,
            'group_session' => GroupSession::class,
            'meeting_minute' => NajmHodaGroupMeetingMinute::class,
            'action_item' => NajmHodaGroupActionItem::class,
            'governance_proposal' => Proposal::class,
            'governance_resolution' => Resolution::class,
            'message' => Message::class,
            'poll' => Poll::class,
            'ticket' => Ticket::class,
        ], true);
    }
}
