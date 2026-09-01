<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\NajmBaharAgreement;
use App\Models\User;
use App\Models\UserExperience;
use App\Models\Address;
use App\Models\InvitationCode;
use App\Models\Setting;
use App\Modules\NajmBahar\Policy\NajmBaharConstitution;
use App\Modules\NajmBahar\Services\AccountBalanceService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\MonetaryService;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Services\ParticipationPointSummaryService;
use App\Services\ReputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NajmBaharController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected AccountBalanceService $balanceService,
        protected TransactionService $transactionService,
        protected MonetaryService $monetaryService,
        protected ReputationService $reputationService,
        protected ParticipationPointSummaryService $participationPointSummaryService,
    ) {
    }

    public function showAgreement()
    {
        $user = auth()->user();
        abort_if($user->isSystemIdentity(), 403, 'System identities cannot use Najm Bahar.');

        $hasAcceptedAgreement = $this->accountService->hasMainAccount($user->id)
            || ! is_null($user->najm_bahar_agreement_accepted_at);

        $agreements = NajmBaharAgreement::whereNull('parent_id')
            ->with('descendants')
            ->orderBy('order')
            ->get();
        $hasExperience = UserExperience::where('user_id', $user->id)->exists();
        $hasAddress = Address::where('user_id', $user->id)->exists();
        $step1Complete = ($user->first_name && $user->last_name && $user->gender && $user->national_id && $user->phone);
        $isProfileComplete = $step1Complete && $hasExperience && $hasAddress;

        return view('najm-bahar.agreement', compact('agreements', 'isProfileComplete', 'hasAcceptedAgreement'));
    }

    public function processAgreement(Request $request)
    {
        $request->validate([
            'agreement_accepted' => 'required|accepted'
        ], [
            'agreement_accepted.required' => 'لطفاً توافقنامه نجم بهار را بپذیرید',
            'agreement_accepted.accepted' => 'لطفاً توافقنامه نجم بهار را بپذیرید'
        ]);

        $user = auth()->user();
        abort_if($user->isSystemIdentity(), 403, 'System identities cannot use Najm Bahar.');

        if ($this->accountService->hasMainAccount($user->id)) {
            return redirect()->route('najm-bahar.dashboard')
                ->with('info', 'شما قبلاً حساب نجم بهار دارید.');
        }

        try {
            DB::transaction(function () use ($user) {
                $userAccount = $this->accountService->createMainAccountForUser(
                    $user->id,
                    'حساب نجم بهار ' . $user->fullName()
                );

                $this->ensureInitialFunding($user, $userAccount);
                $this->processReferralParticipation($user);

                $user->update([
                    'najm_bahar_agreement_accepted_at' => now()
                ]);

                Log::info('NajmBahar account created successfully', [
                    'user_id' => $user->id,
                    'account_number' => $userAccount->account_number,
                ]);
            });

            return redirect()->route('najm-bahar.dashboard')
                ->with('success', 'حساب نجم بهار شما با موفقیت ایجاد شد! برای فعالسازی کامل، حق عضویت سالانه را پرداخت کنید.');
        } catch (\Exception $e) {
            Log::error('NajmBahar account creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'خطا در ایجاد حساب نجم بهار. لطفاً مجدداً تلاش کنید.');
        }
    }

    /**
     * Referral is participation, not a transfer of the new member's dim money.
     * The reputation rule remains configurable; conversion later activates the
     * referrer's own constitutional dim balance through MonetaryService.
     */
    protected function processReferralParticipation(User $user): void
    {
        $invitationCheck = InvitationCode::where('used_by', $user->id)->first();
        if (! $invitationCheck || (int) $invitationCheck->user_id === 171) {
            return;
        }

        $referrer = User::find($invitationCheck->user_id);
        if (! $referrer) {
            return;
        }

        $this->reputationService->applyAction(
            $referrer,
            'invite_member',
            [
                'new_user_id' => $user->id,
                'invitation_code_id' => $invitationCheck->id,
                'economic_rule' => 'participation_points_only_no_dim_transfer',
            ],
            $invitationCheck->id,
            'najm_bahar_membership',
            'invite_member:referrer:' . $referrer->id . ':member:' . $user->id
        );
    }

    public function dashboard()
    {
        $user = auth()->user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        $this->ensureInitialFunding($user, $account);
        $walletBalance = $this->balanceService->aggregate($account);

        $settings = Setting::firstNajmBaharSettings();
        $userCount = User::members()->count();
        $userThreshold = (int) ($settings?->najm_bahar_user_threshold ?? 1111111);
        $initialAmount = NajmBaharConstitution::initialMembershipGol();
        $isThresholdMet = $userCount >= $userThreshold;
        $remainingUsers = max(0, $userThreshold - $userCount);
        $totalMinted = $userCount * $initialAmount;

        $membershipAccountCode = $settings?->najm_bahar_membership_fee_account ?? '0000000000-001';
        $membershipSubAccount = $this->accountService->getSystemSubAccountByCode($membershipAccountCode);
        $membershipBalance = (int) ($membershipSubAccount?->balance ?? 0);

        $accountIds = $this->transactionService->getUserAccountIds($user->id);
        $userTransactionsCount = empty($accountIds)
            ? 0
            : NajmTransaction::where(function ($query) use ($accountIds) {
                $query->whereIn('from_account_id', $accountIds)
                    ->orWhereIn('to_account_id', $accountIds);
            })->count();

        return view('najm-bahar.dashboard', compact(
            'account',
            'walletBalance',
            'userCount',
            'userThreshold',
            'isThresholdMet',
            'remainingUsers',
            'totalMinted',
            'membershipAccountCode',
            'membershipBalance',
            'userTransactionsCount'
        ));
    }

    public function wallet()
    {
        $user = auth()->user();
        $account = $this->accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        $this->ensureInitialFunding($user, $account);
        $walletBalance = $this->balanceService->aggregate($account);
        $this->applyCanonicalWalletBalancesToViewAccount($account, $walletBalance);

        $recentTransactions = $this->transactionService->getUserTransactions($user->id, 10);
        $accountIds = $this->transactionService->getUserAccountIds($user->id);

        $pointSummary = $this->participationPointSummaryService->forUser((int) $user->id);
        $totalPoints = $pointSummary['total_points'];
        $userLevel = $pointSummary['level'];
        $cashedPoints = $pointSummary['cashed_points'];
        $uncashedPoints = $pointSummary['remaining_convertible_points'];
        $convertibleAwardedPoints = $pointSummary['convertible_awarded_points'];
        $ledgerConsumedPoints = $pointSummary['ledger_consumed_points'];
        $legacyCashedPoints = $pointSummary['legacy_cashed_points'];
        $participationReversalPoints = $pointSummary['participation_reversal_points'];

        return view('najm-bahar.wallet', compact(
            'account',
            'walletBalance',
            'recentTransactions',
            'accountIds',
            'totalPoints',
            'userLevel',
            'cashedPoints',
            'uncashedPoints',
            'convertibleAwardedPoints',
            'ledgerConsumedPoints',
            'legacyCashedPoints',
            'participationReversalPoints'
        ));
    }

    private function applyCanonicalWalletBalancesToViewAccount($account, array $walletBalance): void
    {
        // Compatibility boundary for the legacy Blade. These assignments only
        // affect the in-memory model used for rendering; nothing is persisted.
        // They make all legacy `$account->balance*` reads display the canonical
        // aggregate wallet, including active child/sub-account balances.
        $account->setAttribute('balance', (int) $walletBalance['total']);
        $account->setAttribute('balance_active', (int) $walletBalance['active']);
        $account->setAttribute('balance_faded', (int) $walletBalance['dim']);
    }

    private function ensureInitialFunding(User $user, $account): void
    {
        $hasInitialFunding = NajmTransaction::where('to_account_id', $account->id)
            ->where('metadata->type', 'initial_funding')
            ->exists();

        if (! $hasInitialFunding) {
            $this->monetaryService->issueMembershipCredit($account, $user->id);
            return;
        }

        if ((int) $account->balance > 0
            && (int) $account->balance_active === 0
            && (int) $account->balance_faded === 0) {
            $this->monetaryService->repairLegacyUnbucketedBalance($account);
        }
    }
}
