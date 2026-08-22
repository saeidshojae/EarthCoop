<?php

namespace App\Enums\Elections;

enum ElectionResponsibilityOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Ineligible = 'ineligible';
}
