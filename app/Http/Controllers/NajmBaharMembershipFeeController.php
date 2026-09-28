<?php

namespace App\Http\Controllers;

use App\Helpers\BaharMoney;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\Api\NajmBaharMembershipFeeApplicationService;
use App\Modules\NajmBahar\Services\Api\NajmBaharMembershipFeeException;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Modules\NajmBahar\Services\TreasuryService;
use App\Services\MembershipFeeStatusService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NajmBaharMembershipFeeController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected AccountBalanceService $balanceService,
        protected MonetaryPolicyService $monetaryPolicy,
        protected TreasuryService $treasuryService,
        protected MembershipFeeStatusService $membershipFeeStatus,
        protected NajmBaharMembershipFeeApplicationService $membershipFeeApplication,
    ) {
    }

    public function getInfo()
    {
        $user = Auth::user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return response()->json(['error' => 'حساب نجم بهار یافت نشد'], 404);
        }

        $hasPaid = $this->membershipFeeStatus->hasPaidCurrentMembershipFee($user);
        $wallet = $this->balanceService->aggregate($account);

        $membershipDate = $user->created_at;
        $currentYear = now()->year;
        $nextAnniversary = $membershipDate->copy()->setYear($currentYear);
        if (now()->greaterThanOrEqualTo($nextAnniversary)) {
            $nextAnniversary->addYear();
        }

        [$operationsAmount, $insuranceAmount, $burnAmount] = $this->membershipSplit();
        $total = $this->membershipFeeAmount();

        $subAccounts = SubAccount::where('account_id', $account->id)
            ->where('status', 1)
            ->orderBy('created_at')
            ->get();

        $mainActive = (int) ($account->balance_active ?? 0);
        $mainDim = (int) ($account->balance_faded ?? 0);
        $canPayFromDim = $mainDim >= $total;
        $canPayFromActive = (int) $wallet['active'] >= $total;

        $defaultSubAccount = $subAccounts->first(fn ($sub) => (int) ($sub->balance_active ?? 0) >= $total)
            ?? $subAccounts->first();
        $defaultSubActive = (int) ($defaultSubAccount?->balance_active ?? 0);
        $hasEnoughBalance = $canPayFromDim || $mainActive >= $total || $defaultSubActive >= $total;
        $requiresSubAccount = ! $canPayFromDim && $mainActive < $total && $defaultSubAccount === null;

        // Preserve the existing web transparency surface. The v1 read API does
        // not bootstrap treasury accounts, so mobile GET remains strictly read-only.
        $funds = $this->treasuryService->ensureDefaultFunds();

        return response()->json([
            'has_paid' => $hasPaid,
            'total_fee' => $total,
            'total_fee_formatted' => BaharMoney::formatDecimal($total),
            'balance_dim' => (int) $wallet['dim'],
            'balance_dim_formatted' => BaharMoney::formatDecimal((int) $wallet['dim']),
            'balance_active' => (int) $wallet['active'],
            'balance_active_formatted' => BaharMoney::formatDecimal((int) $wallet['active']),
            'wallet_total' => (int) $wallet['total'],
            'wallet_total_formatted' => BaharMoney::formatDecimal((int) $wallet['total']),
            'can_pay_from_dim' => $canPayFromDim,
            'can_pay_from_active' => $canPayFromActive,
            'default_payment_source' => $canPayFromDim ? 'dim' : 'active',
            'payment_source_required' => true,
            'policy_version_id' => $this->monetaryPolicy->versionId(),
            'has_enough_balance' => $hasEnoughBalance,
            'requires_sub_account' => $requiresSubAccount,
            'main_active_balance' => $mainActive,
            'main_active_formatted' => BaharMoney::formatDecimal($mainActive),
            'sub_account' => $defaultSubAccount ? [
                'id' => $defaultSubAccount->id,
                'code' => $defaultSubAccount->sub_account_code,
                'name' => $defaultSubAccount->name,
                'balance_active' => $defaultSubActive,
                'balance_active_formatted' => BaharMoney::formatDecimal($defaultSubActive),
            ] : null,
            'create_subaccount_url' => route('najm-bahar.sub-accounts.create'),
            'create_subaccount_store_url' => route('najm-bahar.sub-accounts.store'),
            'transfer_url' => route('najm-bahar.transfer'),
            'transfer_to_url' => $defaultSubAccount
                ? route('najm-bahar.sub-accounts.transfer-to', ['subAccount' => $defaultSubAccount->id])
                : null,
            'sub_accounts' => $subAccounts->map(fn ($sub) => [
                'id' => $sub->id,
                'code' => $sub->sub_account_code,
                'name' => $sub->name,
                'balance_active' => (int) ($sub->balance_active ?? 0),
                'balance_active_formatted' => BaharMoney::formatDecimal((int) ($sub->balance_active ?? 0)),
            ])->values(),
            'membership_date' => $membershipDate->format('Y-m-d'),
            'membership_date_formatted' => $membershipDate->locale('fa')->isoFormat('jYYYY/jMM/jDD'),
            'next_anniversary' => $nextAnniversary->format('Y-m-d'),
            'next_anniversary_formatted' => $nextAnniversary->locale('fa')->isoFormat('jYYYY/jMM/jDD'),
            'breakdown' => [
                [
                    'name' => 'صندوق حقوق و هزینه‌ها',
                    'account' => $funds[TreasuryService::OPERATIONS_SALARY]->account->account_number,
                    'amount' => $operationsAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($operationsAmount),
                ],
                [
                    'name' => 'صندوق بیمه مرکزی',
                    'account' => $funds[TreasuryService::CENTRAL_INSURANCE]->account->account_number,
                    'amount' => $insuranceAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($insuranceAmount),
                ],
                [
                    'name' => 'صندوق امحای پول',
                    'account' => $funds[TreasuryService::MONEY_DESTRUCTION]->account->account_number,
                    'amount' => $burnAmount,
                    'amount_formatted' => BaharMoney::formatDecimal($burnAmount),
                ],
            ],
        ]);
    }

    public function pay(Request $request)
    {
        $validated = $request->validate([
            'payment_source' => 'required|in:dim,active',
            'sub_account_id' => 'nullable|integer',
        ], [
            'payment_source.required' => 'منبع پرداخت حق عضویت را مشخص کنید.',
            'payment_source.in' => 'منبع پرداخت حق عضویت معتبر نیست.',
        ]);

        $user = Auth::user();

        try {
            $result = $this->membershipFeeApplication->pay(
                $user,
                (string) $validated['payment_source'],
                isset($validated['sub_account_id']) ? (int) $validated['sub_account_id'] : null,
            );

            return redirect()->route('najm-bahar.dashboard')
                ->with('success', 'حق عضویت سالانه با موفقیت پرداخت شد. مبلغ: '
                    .BaharMoney::formatDecimal((int) $result['fee_gol']).' بهار');
        } catch (NajmBaharMembershipFeeException $exception) {
            $message = match ($exception->errorCode) {
                'already_paid' => 'شما برای سال جاری حق عضویت سالانه را پرداخت کرده‌اید',
                'insufficient_dim' => 'موجودی کمرنگ برای پرداخت حق عضویت کافی نیست.',
                'insufficient_available_funds' => 'موجودی فعال برای پرداخت حق عضویت کافی نیست.',
                'membership_fee_policy_invalid' => 'سیاست توزیع حق عضویت معتبر نیست.',
                default => $exception->getMessage(),
            };

            return back()->with('error', $message);
        } catch (ModelNotFoundException) {
            return back()->with('error', 'حساب نجم بهار یا حساب فرعی انتخاب‌شده یافت نشد');
        } catch (\Throwable $exception) {
            Log::error('NajmBahar membership fee payment failed', [
                'user_id' => $user?->id,
                'payment_source' => $validated['payment_source'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'خطا در پرداخت حق عضویت: '.$exception->getMessage());
        }
    }

    private function membershipSplit(): array
    {
        $split = $this->membershipFeeApplication->split();

        return [
            $split[TreasuryService::OPERATIONS_SALARY],
            $split[TreasuryService::CENTRAL_INSURANCE],
            $split[TreasuryService::MONEY_DESTRUCTION],
        ];
    }

    private function membershipFeeAmount(): int
    {
        return $this->membershipFeeApplication->feeAmount();
    }
}
