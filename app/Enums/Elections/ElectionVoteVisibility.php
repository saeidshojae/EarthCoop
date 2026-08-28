<?php

namespace App\Enums\Elections;

enum ElectionVoteVisibility: string
{
    case Confidential = 'confidential';
    case AllMembers = 'all_members';
    case ElectedOfficials = 'elected_officials';
}
