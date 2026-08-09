<?php

namespace App\Modules\NajmBahar\Policy;

use App\Helpers\BaharMoney;

final class NajmBaharConstitution
{
    /**
     * Each valid member receives exactly 10,000 Bahar of membership-originated
     * monetary capacity. This is a constitutional rule, not an admin setting.
     */
    public const INITIAL_MEMBERSHIP_BAHAR = 10_000;

    /**
     * Membership-originated money always enters the system as dim money.
     * Activation is a later, policy-governed state transition.
     */
    public const INITIAL_ACTIVE_PERCENTAGE = 0;

    public static function initialMembershipGol(): int
    {
        return BaharMoney::toGolFromBahar(self::INITIAL_MEMBERSHIP_BAHAR);
    }

    public static function initialActiveGol(): int
    {
        return 0;
    }

    public static function initialDimGol(): int
    {
        return self::initialMembershipGol();
    }

    private function __construct()
    {
    }
}
