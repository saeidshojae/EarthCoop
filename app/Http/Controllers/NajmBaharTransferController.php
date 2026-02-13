<?php

namespace App\Http\Controllers;

use App\Helpers\BaharMoney;
use App\Models\Group;
use App\Models\GroupUser;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Services\NajmBaharAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;

class NajmBaharTransferController extends Controller
{
    private function resolveMoneyState(SubAccount $subAccount, int $amount): string
    {
        $faded = intval($subAccount->balance_faded ?? 0);
        $active = intval($subAccount->balance_active ?? 0);

        if ($faded >= $amount) {
            return 'faded';
        }

        if ($active >= $amount) {
            return 'active';
        }

        if (($faded + $active) >= $amount) {
            throw new \RuntimeException('موجودی کافی است اما بین فعال و منقضی تقسیم شده است. لطفا انتقال را در دو مرحله انجام دهید.');
        }

        throw new \RuntimeException('موجودی حساب فرعی کافی نیست.');
    }
    public function create(AccountService $accountService, SubAccountService $subAccountService)
    {
        $user = auth()->user();
        $account = $accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        $subAccounts = $subAccountService->getSubAccountsForAccount($account->id);

        return view('najm-bahar.transfer', compact('account', 'subAccounts'));
    }

    public function store(Request $request, AccountService $accountService, SubAccountService $subAccountService)
    {
        $user = auth()->user();
        $account = $accountService->getMainAccountForUser($user->id);

        if (! $account) {
            return redirect()->route('najm-bahar.agreement')
                ->with('info', 'ابتدا باید حساب نجم بهار خود را ایجاد کنید.');
        }

        $validated = $request->validate([
            'source_sub_account_id' => 'required|integer|exists:najm_sub_accounts,id',
            'target_sub_account_code' => 'required|string|max:50',
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
            'transaction_type' => 'required|in:immediate,scheduled',
            'execute_at' => 'nullable|date',
        ]);

        $sourceSubAccount = SubAccount::where('id', $validated['source_sub_account_id'])
            ->where('account_id', $account->id)
            ->where('status', 1)
            ->first();

        if (! $sourceSubAccount) {
            return back()->with('error', 'دسترسی غیرمجاز.')->withInput();
        }

        $normalizedCode = preg_replace('/\s+/', '', $validated['target_sub_account_code']);
        $normalizedCode = str_replace('/', '-', $normalizedCode);

        $targetSubAccount = SubAccount::where('sub_account_code', $normalizedCode)
            ->where('status', 1)
            ->first();

        if (! $targetSubAccount) {
            return back()->with('error', 'حساب فرعی مقصد یافت نشد یا غیرفعال است.')->withInput();
        }

        if ($targetSubAccount->id === $sourceSubAccount->id) {
            return back()->with('error', 'حساب مبدا و مقصد نمی توانند یکسان باشند.')->withInput();
        }

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return back()->with('error', 'مبلغ باید بزرگتر از صفر باشد.')->withInput();
        }

        $sourceSubAccount->loadMissing('account');
        $targetSubAccount->loadMissing('account');
        $transactionType = $validated['transaction_type'];
        $moneyState = $this->resolveMoneyState($sourceSubAccount, $amount);

        try {
            if ($transactionType === 'scheduled') {
                if (! $request->filled('execute_at')) {
                    return back()->with('error', 'زمان اجرای تراکنش الزامی است.')->withInput();
                }

                $executeAt = Carbon::parse($validated['execute_at']);
                if ($executeAt->lessThanOrEqualTo(now())) {
                    return back()->with('error', 'زمان اجرا باید در آینده باشد.')->withInput();
                }

                $tx = NajmTransaction::create([
                    'from_account_id' => null,
                    'to_account_id' => null,
                    'amount' => $amount,
                    'type' => 'scheduled',
                    'status' => 'pending',
                    'scheduled_at' => $executeAt,
                    'metadata' => [
                        'transfer_type' => 'subaccount',
                        'from_sub_account_id' => $sourceSubAccount->id,
                        'to_sub_account_id' => $targetSubAccount->id,
                        'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                        'to_sub_account_code' => $targetSubAccount->sub_account_code,
                        'money_state' => $moneyState,
                    ],
                    'description' => $validated['description'] ?? null,
                ]);

                ScheduledTransaction::create([
                    'transaction_id' => $tx->id,
                    'execute_at' => $executeAt,
                    'status' => 'scheduled',
                    'payload' => [
                        'type' => 'subaccount_transfer',
                        'from_sub_account_id' => $sourceSubAccount->id,
                        'to_sub_account_id' => $targetSubAccount->id,
                        'amount' => $amount,
                        'money_state' => $moneyState,
                        'description' => $validated['description'] ?? null,
                        'metadata' => [
                            'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                            'to_sub_account_code' => $targetSubAccount->sub_account_code,
                            'from_account_number' => $sourceSubAccount->account?->account_number,
                            'actor_user_id' => $user->id,
                            'money_state' => $moneyState,
                        ],
                    ],
                ]);

                NajmBaharAuditLogger::log([
                    'actor_user_id' => $user->id,
                    'action' => 'subaccount.transfer_between_scheduled',
                    'account_number' => $sourceSubAccount->account?->account_number,
                    'sub_account_code' => $sourceSubAccount->sub_account_code,
                    'amount' => $amount,
                    'direction' => 'subaccount_transfer',
                    'description' => $validated['description'] ?? 'انتقال زمان بندی شده',
                    'meta' => [
                        'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                        'to_sub_account_code' => $targetSubAccount->sub_account_code,
                        'execute_at' => $executeAt->toDateTimeString(),
                        'transaction_id' => $tx->id,
                    ],
                ]);

                return redirect()->route('najm-bahar.transfer')
                    ->with('success', 'انتقال زمان بندی شده ثبت شد.');
            }

            $subAccountService->transferBetweenSubAccounts(
                $sourceSubAccount->id,
                $targetSubAccount->id,
                $amount,
                $validated['description'] ?? null,
                $moneyState
            );

            NajmBaharAuditLogger::log([
                'actor_user_id' => $user->id,
                'action' => 'subaccount.transfer_between',
                'account_number' => $sourceSubAccount->account?->account_number,
                'sub_account_code' => $sourceSubAccount->sub_account_code,
                'amount' => $amount,
                'direction' => 'subaccount_transfer',
                'description' => $validated['description'] ?? 'انتقال بین حساب های فرعی',
                'meta' => [
                    'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                    'to_sub_account_code' => $targetSubAccount->sub_account_code,
                ],
            ]);

            return redirect()->route('najm-bahar.transfer')
                ->with('success', 'انتقال وجه با موفقیت انجام شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در انتقال وجه: ' . $e->getMessage())->withInput();
        }
    }

    public function createForGroup(Group $group, AccountService $accountService, SubAccountService $subAccountService)
    {
        $account = $this->getGroupAccountOrNull($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به انتقال وجه گروه برای شما مجاز نیست.');
        }

        $subAccounts = $subAccountService->getSubAccountsForAccount($account->id);
        $routePrefix = 'groups.najm-bahar';
        $routeParams = ['group' => $group->id];
        $transferOwnerLabel = 'گروه ' . $group->name;
        $requireDescription = true;

        return view('najm-bahar.transfer', compact('account', 'subAccounts', 'routePrefix', 'routeParams', 'transferOwnerLabel', 'requireDescription'));
    }

    public function storeForGroup(Request $request, Group $group, AccountService $accountService, SubAccountService $subAccountService)
    {
        $account = $this->getGroupAccountOrNull($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به انتقال وجه گروه برای شما مجاز نیست.');
        }

        $validated = $request->validate([
            'source_sub_account_id' => 'required|integer|exists:najm_sub_accounts,id',
            'target_sub_account_code' => 'required|string|max:50',
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'required|string|max:500',
            'transaction_type' => 'required|in:immediate,scheduled',
            'execute_at' => 'nullable|date',
        ]);

        $sourceSubAccount = SubAccount::where('id', $validated['source_sub_account_id'])
            ->where('account_id', $account->id)
            ->where('status', 1)
            ->first();

        if (! $sourceSubAccount) {
            return back()->with('error', 'دسترسی غیرمجاز.')->withInput();
        }

        $normalizedCode = preg_replace('/\s+/', '', $validated['target_sub_account_code']);
        $normalizedCode = str_replace('/', '-', $normalizedCode);

        $targetSubAccount = SubAccount::where('sub_account_code', $normalizedCode)
            ->where('status', 1)
            ->first();

        if (! $targetSubAccount) {
            return back()->with('error', 'حساب فرعی مقصد یافت نشد یا غیرفعال است.')->withInput();
        }

        if ($targetSubAccount->id === $sourceSubAccount->id) {
            return back()->with('error', 'حساب مبدا و مقصد نمی توانند یکسان باشند.')->withInput();
        }

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return back()->with('error', 'مبلغ باید بزرگتر از صفر باشد.')->withInput();
        }

        $sourceSubAccount->loadMissing('account');
        $targetSubAccount->loadMissing('account');
        $transactionType = $validated['transaction_type'];
        $moneyState = $this->resolveMoneyState($sourceSubAccount, $amount);
        $actorName = trim(($request->user()->first_name ?? '') . ' ' . ($request->user()->last_name ?? ''));

        try {
            if ($transactionType === 'scheduled') {
                if (! $request->filled('execute_at')) {
                    return back()->with('error', 'زمان اجرای تراکنش الزامی است.')->withInput();
                }

                $executeAt = Carbon::parse($validated['execute_at']);
                if ($executeAt->lessThanOrEqualTo(now())) {
                    return back()->with('error', 'زمان اجرا باید در آینده باشد.')->withInput();
                }

                $tx = NajmTransaction::create([
                    'from_account_id' => null,
                    'to_account_id' => null,
                    'amount' => $amount,
                    'type' => 'scheduled',
                    'status' => 'pending',
                    'scheduled_at' => $executeAt,
                    'metadata' => [
                        'transfer_type' => 'subaccount',
                        'from_sub_account_id' => $sourceSubAccount->id,
                        'to_sub_account_id' => $targetSubAccount->id,
                        'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                        'to_sub_account_code' => $targetSubAccount->sub_account_code,
                        'group_id' => $group->id,
                        'actor_user_id' => $request->user()->id,
                        'actor_name' => $actorName,
                        'money_state' => $moneyState,
                    ],
                    'description' => $validated['description'],
                ]);

                ScheduledTransaction::create([
                    'transaction_id' => $tx->id,
                    'execute_at' => $executeAt,
                    'status' => 'scheduled',
                    'payload' => [
                        'type' => 'subaccount_transfer',
                        'from_sub_account_id' => $sourceSubAccount->id,
                        'to_sub_account_id' => $targetSubAccount->id,
                        'amount' => $amount,
                        'money_state' => $moneyState,
                        'description' => $validated['description'],
                        'metadata' => [
                            'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                            'to_sub_account_code' => $targetSubAccount->sub_account_code,
                            'from_account_number' => $sourceSubAccount->account?->account_number,
                            'group_id' => $group->id,
                            'actor_user_id' => $request->user()->id,
                            'actor_name' => $actorName,
                            'money_state' => $moneyState,
                        ],
                    ],
                ]);

                NajmBaharAuditLogger::logGroupAction($group, $request->user(), [
                    'action' => 'subaccount.transfer_between_scheduled',
                    'account_number' => $sourceSubAccount->account?->account_number,
                    'sub_account_code' => $sourceSubAccount->sub_account_code,
                    'amount' => $amount,
                    'direction' => 'subaccount_transfer',
                    'description' => $validated['description'],
                    'meta' => [
                        'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                        'to_sub_account_code' => $targetSubAccount->sub_account_code,
                        'execute_at' => $executeAt->toDateTimeString(),
                        'transaction_id' => $tx->id,
                        'actor_user_id' => $request->user()->id,
                        'actor_name' => $actorName,
                    ],
                ]);

                return redirect()->route('groups.najm-bahar.transfer', $group)
                    ->with('success', 'انتقال زمان بندی شده ثبت شد.');
            }

            $subAccountService->transferBetweenSubAccounts(
                $sourceSubAccount->id,
                $targetSubAccount->id,
                $amount,
                $validated['description'],
                $moneyState
            );

            NajmBaharAuditLogger::logGroupAction($group, $request->user(), [
                'action' => 'subaccount.transfer_between',
                'account_number' => $sourceSubAccount->account?->account_number,
                'sub_account_code' => $sourceSubAccount->sub_account_code,
                'amount' => $amount,
                'direction' => 'subaccount_transfer',
                'description' => $validated['description'],
                'meta' => [
                    'from_sub_account_code' => $sourceSubAccount->sub_account_code,
                    'to_sub_account_code' => $targetSubAccount->sub_account_code,
                    'actor_user_id' => $request->user()->id,
                    'actor_name' => $actorName,
                ],
            ]);

            return redirect()->route('groups.najm-bahar.transfer', $group)
                ->with('success', 'انتقال وجه با موفقیت انجام شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در انتقال وجه: ' . $e->getMessage())->withInput();
        }
    }

    private function getGroupAccountOrNull(Group $group, AccountService $accountService)
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $isManager = GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereIn('role', [2, 3])
            ->where('status', 1)
            ->exists();

        if (! $isManager) {
            return null;
        }

        return $accountService->ensureLegalEntityAccountForGroup($group);
    }

    public function previewTarget(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        return $this->buildTargetPreview($validated['code']);
    }

    public function previewTargetForGroup(Request $request, Group $group, AccountService $accountService)
    {
        $account = $this->getGroupAccountOrNull($group, $accountService);
        if (! $account) {
            return response()->json(['message' => 'forbidden'], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        return $this->buildTargetPreview($validated['code']);
    }

    private function buildTargetPreview(string $rawCode)
    {
        $normalizedCode = preg_replace('/\s+/', '', $rawCode);
        $normalizedCode = str_replace('/', '-', $normalizedCode);

        $subAccount = SubAccount::where('sub_account_code', $normalizedCode)
            ->where('status', 1)
            ->first();

        if (! $subAccount) {
            return response()->json(['message' => 'not_found'], 404);
        }

        $subAccount->loadMissing('account');
        $account = $subAccount->account;

        $ownerType = 'unknown';
        $ownerName = $account?->name ?? '';

        if ($account?->type === 'legal_entity') {
            $groupId = $account->meta['group_id'] ?? null;
            if ($groupId) {
                $group = Group::find($groupId);
                if ($group) {
                    $ownerType = 'group';
                    $ownerName = $group->name;
                }
            }
        } elseif ($account?->user_id) {
            $user = User::find($account->user_id);
            if ($user) {
                $ownerType = 'user';
                $ownerName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            }
        } elseif ($account?->type === 'system') {
            $ownerType = 'system';
            $ownerName = 'سیستم';
        }

        return response()->json([
            'owner_type' => $ownerType,
            'owner_name' => $ownerName ?: ($account?->name ?? '-'),
            'sub_account_name' => $subAccount->name,
            'sub_account_code' => str_replace('-', '/', $subAccount->sub_account_code),
        ]);
    }
}
