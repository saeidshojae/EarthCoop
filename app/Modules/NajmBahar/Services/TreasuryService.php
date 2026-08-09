<?php

namespace App\Modules\NajmBahar\Services;

use App\Modules\NajmBahar\Models\TreasuryFund;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public const OPERATIONS_SALARY = 'operations_salary';
    public const CENTRAL_INSURANCE = 'central_insurance';
    public const MONEY_DESTRUCTION = 'money_destruction';
    public const IDLE_TAX = 'idle_tax';

    public function __construct(private readonly AccountService $accountService)
    {
    }

    /**
     * Register the constitutional/system treasury funds against their actual
     * Najm Bahar accounts. This does not move or mint any money.
     */
    public function ensureDefaultFunds(): array
    {
        return DB::transaction(function () {
            $systemAccount = $this->accountService->getSystemAccount();
            $this->accountService->ensureDefaultSystemSubAccounts($systemAccount);

            $definitions = [
                self::OPERATIONS_SALARY => [
                    'suffix' => 1,
                    'name' => 'صندوق حقوق و هزینه‌های EarthCoop',
                    'purpose' => 'پرداخت حقوق، هزینه‌های جاری و زیرساخت‌های مصوب EarthCoop',
                ],
                self::CENTRAL_INSURANCE => [
                    'suffix' => 2,
                    'name' => 'صندوق بیمه مرکزی',
                    'purpose' => 'ذخیره و پشتیبانی سیاست‌های بیمه مرکزی و تعهدات مصوب',
                ],
                self::MONEY_DESTRUCTION => [
                    'suffix' => 0,
                    'name' => 'صندوق امحای پول',
                    'purpose' => 'تأمین ظرفیت امحای پول و تسویه تعهدات retirement پولی',
                ],
                self::IDLE_TAX => [
                    'suffix' => 3,
                    'name' => 'صندوق مالیات پول راکد',
                    'purpose' => 'تجمیع مالیات پول راکد و بازتخصیص شفاف آن طبق سیاست اقتصادی',
                ],
            ];

            $funds = [];
            foreach ($definitions as $code => $definition) {
                $subAccountCode = AccountNumberService::makeSubAccountCode(
                    $systemAccount->account_number,
                    $definition['suffix']
                );

                $subAccount = $this->accountService->getSystemSubAccountByCode($subAccountCode);
                if (! $subAccount) {
                    throw new \RuntimeException("System treasury sub-account {$subAccountCode} is missing.");
                }

                $account = $this->accountService->ensureSubAccountAccount($subAccount);

                $funds[$code] = TreasuryFund::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $definition['name'],
                        'account_id' => $account->id,
                        'purpose' => $definition['purpose'],
                        'is_active' => true,
                    ]
                );
            }

            return $funds;
        });
    }

    public function get(string $code): TreasuryFund
    {
        $this->ensureDefaultFunds();

        return TreasuryFund::with('account')->where('code', $code)->firstOrFail();
    }
}
