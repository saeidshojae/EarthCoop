<?php

namespace App\Enums\Elections;

enum ElectionBallotCommentVisibility: string
{
    /** Visible to all members allowed to view the election context. */
    case AllMembers = 'all_members';

    /** Visible only to elected manager/inspector office-holders. */
    case ElectedOfficials = 'elected_officials';

    /** Visible only to the member whose vote was cast/changed/withdrawn. */
    case SubjectOnly = 'subject_only';
}
