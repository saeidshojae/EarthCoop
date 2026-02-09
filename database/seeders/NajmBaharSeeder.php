<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;

class NajmBaharSeeder extends Seeder
{
    public function run()
    {
        // system main account
        $systemAccountNumber = AccountNumberService::makeSystemAccountNumber();

        $system = Account::firstOrCreate([
            'account_number' => $systemAccountNumber,
        ], [
            'name' => 'EarthCoop System Main Account',
            'type' => 'system',
            'balance' => 0,
            'status' => 1,
        ]);

        // create three sub accounts: membership, insurance, burn
        $subs = [
            ['code' => '001', 'name' => 'Membership Fees'],
            ['code' => '002', 'name' => 'Insurance Fund'],
            ['code' => '003', 'name' => 'Burn Account'],
        ];

        foreach ($subs as $s) {
            $code = $systemAccountNumber . '-' . $s['code'];
            SubAccount::firstOrCreate(['sub_account_code' => $code], [
                'account_id' => $system->id,
                'name' => $s['name'],
                'balance' => 0,
                'status' => 1,
            ]);
        }
    }
}
