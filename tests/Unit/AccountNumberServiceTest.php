<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Modules\NajmBahar\Services\AccountNumberService;

class AccountNumberServiceTest extends TestCase
{
    public function test_make_main_account_number_for_user()
    {
        $num = AccountNumberService::makeMainAccountNumberForUser(254);
        $this->assertEquals('1000000254', $num);
    }

    public function test_system_account_number()
    {
        $this->assertEquals('0000000000', AccountNumberService::makeSystemAccountNumber());
    }

    public function test_make_sub_account_code()
    {
        $code = AccountNumberService::makeSubAccountCode('0000000000', 1);
        $this->assertEquals('0000000000-001', $code);
    }
}
