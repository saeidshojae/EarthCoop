<?php

namespace App\Enums\Elections;

enum ElectionAcceptanceStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    /**
     * Transitional adapter for the inconsistent legacy Candidate contract.
     *
     * Historical code used 1=offered/pending, 2=accepted and 0 both for a
     * rejected offer and for candidates that were never offered a seat.
     * Therefore legacy 0 is only interpretable when the caller can prove an
     * offer existed; otherwise it deliberately returns null.
     */
    public static function fromLegacyCandidateStatus(
        int|string|null $value,
        bool $wasOffered = false,
    ): ?self {
        if ($value === null) {
            return null;
        }

        return match ((string) $value) {
            '1', self::Pending->value => self::Pending,
            '2', self::Accepted->value => self::Accepted,
            '0' => $wasOffered ? self::Declined : null,
            self::Declined->value => self::Declined,
            self::Expired->value => self::Expired,
            default => throw new \InvalidArgumentException("Unsupported election acceptance status [{$value}]."),
        };
    }
}
