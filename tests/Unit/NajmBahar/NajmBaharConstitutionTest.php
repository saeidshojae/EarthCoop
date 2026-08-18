<?php

namespace Tests\Unit\NajmBahar;

use App\Helpers\BaharMoney;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use PHPUnit\Framework\TestCase;

class NajmBaharConstitutionTest extends TestCase
{
    public function test_initial_membership_credit_is_exactly_ten_thousand_bahar(): void
    {
        $this->assertSame(10_000, NajmBaharConstitution::INITIAL_MEMBERSHIP_BAHAR);
        $this->assertSame(
            BaharMoney::toGolFromBahar(10_000),
            NajmBaharConstitution::initialMembershipGol()
        );
    }

    public function test_initial_membership_credit_is_one_hundred_percent_dim(): void
    {
        $this->assertSame(0, NajmBaharConstitution::INITIAL_ACTIVE_PERCENTAGE);
        $this->assertSame(0, NajmBaharConstitution::initialActiveGol());
        $this->assertSame(
            NajmBaharConstitution::initialMembershipGol(),
            NajmBaharConstitution::initialDimGol()
        );
    }
}
