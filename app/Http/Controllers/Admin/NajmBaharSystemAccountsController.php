<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Helpers\BaharMoney;
use App\Models\AdminActionLog;
use Illuminate\Http\Request;

class NajmBaharSystemAccountsController extends Controller
{
    public function index(AccountService $accountService)
    {
        $systemAccount = $accountService->getSystemAccount();
        $accountService->ensureDefaultSystemSubAccounts($systemAccount);

        $subAccounts = SubAccount::where('account_id', $systemAccount->id)
            ->orderBy('sub_account_code')
            ->get();

        return view('admin.najm-bahar.system-accounts', compact('systemAccount', 'subAccounts'));
    }

    public function storeSubAccount(Request $request, AccountService $accountService, SubAccountService $subAccountService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $systemAccount = $accountService->getSystemAccount();
        $subAccount = $subAccountService->createSubAccount($systemAccount->id, $validated['name']);
        $accountService->ensureSubAccountAccount($subAccount);

        $this->logAction('najm_bahar.subaccount.create', 'subaccount', $subAccount->id, 'Create system subaccount', [
            'name' => $subAccount->name,
            'code' => $subAccount->sub_account_code,
        ]);

        return redirect()->route('admin.najm-bahar.system-accounts.index')
            ->with('success', 'حساب فرعی جدید ایجاد شد.');
    }

    public function updateSubAccount(Request $request, SubAccount $subAccount, AccountService $accountService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $systemAccount = $accountService->getSystemAccount();
        if ($subAccount->account_id !== $systemAccount->id) {
            abort(404);
        }

        $subAccount->name = $validated['name'];
        $subAccount->status = (int) $validated['status'];
        $subAccount->save();

        $accountService->ensureSubAccountAccount($subAccount);

        $this->logAction('najm_bahar.subaccount.update', 'subaccount', $subAccount->id, 'Update system subaccount', [
            'name' => $subAccount->name,
            'status' => $subAccount->status,
        ]);

        return redirect()->route('admin.najm-bahar.system-accounts.index')
            ->with('success', 'حساب فرعی بروزرسانی شد.');
    }

    public function transfer(Request $request, AccountService $accountService, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'from_account' => 'required|string',
            'to_account' => 'required|string|different:from_account',
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'required|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return redirect()->route('admin.najm-bahar.system-accounts.index')
                ->with('error', 'مبلغ باید بزرگتر از صفر باشد.');
        }

        $systemAccount = $accountService->getSystemAccount();
        $accountService->ensureDefaultSystemSubAccounts($systemAccount);

        $allowedAccounts = array_merge(
            [$systemAccount->account_number],
            SubAccount::where('account_id', $systemAccount->id)->pluck('sub_account_code')->all()
        );

        if (!in_array($validated['from_account'], $allowedAccounts, true) || !in_array($validated['to_account'], $allowedAccounts, true)) {
            return redirect()->route('admin.najm-bahar.system-accounts.index')
                ->with('error', 'انتخاب حساب معتبر نیست.');
        }

        $transactionService->transfer(
            $validated['from_account'],
            $validated['to_account'],
            $amount,
            $validated['description'],
            ['type' => 'system_transfer', 'admin_id' => auth()->id()]
        );

        $this->syncSubAccountBalances($systemAccount, $accountService);

        $this->logAction('najm_bahar.system.transfer', 'system_account', $systemAccount->id, $validated['description'], [
            'from' => $validated['from_account'],
            'to' => $validated['to_account'],
            'amount' => $amount,
        ]);

        return redirect()->route('admin.najm-bahar.system-accounts.index')
            ->with('success', 'انتقال بین حساب‌های سیستمی انجام شد.');
    }

    public function adjust(Request $request, AccountService $accountService, TransactionService $transactionService)
    {
        $validated = $request->validate([
            'account' => 'required|string',
            'direction' => 'required|in:credit,debit',
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'required|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return redirect()->route('admin.najm-bahar.system-accounts.index')
                ->with('error', 'مبلغ باید بزرگتر از صفر باشد.');
        }

        $systemAccount = $accountService->getSystemAccount();
        $accountService->ensureDefaultSystemSubAccounts($systemAccount);

        $allowedAccounts = array_merge(
            [$systemAccount->account_number],
            SubAccount::where('account_id', $systemAccount->id)->pluck('sub_account_code')->all()
        );

        if (!in_array($validated['account'], $allowedAccounts, true)) {
            return redirect()->route('admin.najm-bahar.system-accounts.index')
                ->with('error', 'انتخاب حساب معتبر نیست.');
        }

        $transactionService->adjust(
            $validated['account'],
            $amount,
            $validated['direction'],
            $validated['description'],
            ['type' => 'system_adjustment', 'admin_id' => auth()->id()]
        );

        $this->syncSubAccountBalances($systemAccount, $accountService);

        $this->logAction('najm_bahar.system.adjust', 'system_account', $systemAccount->id, $validated['description'], [
            'account' => $validated['account'],
            'direction' => $validated['direction'],
            'amount' => $amount,
        ]);

        return redirect()->route('admin.najm-bahar.system-accounts.index')
            ->with('success', 'تعدیل حساب سیستمی ثبت شد.');
    }

    public function ensureDefaults(AccountService $accountService)
    {
        $systemAccount = $accountService->getSystemAccount();
        $accountService->ensureDefaultSystemSubAccounts($systemAccount);

        $this->logAction('najm_bahar.system.ensure_defaults', 'system_account', $systemAccount->id, 'Ensure default system subaccounts', []);

        return redirect()->route('admin.najm-bahar.system-accounts.index')
            ->with('success', 'حساب‌های پیش‌فرض سیستم بررسی و تکمیل شد.');
    }

    private function syncSubAccountBalances($systemAccount, AccountService $accountService): void
    {
        $subAccounts = SubAccount::where('account_id', $systemAccount->id)->get();

        foreach ($subAccounts as $subAccount) {
            $account = $accountService->ensureSubAccountAccount($subAccount);
            if ($subAccount->balance !== $account->balance) {
                $subAccount->balance = $account->balance;
                $subAccount->save();
            }
        }
    }

    private function logAction(string $action, ?string $targetType, ?int $targetId, string $description, array $metadata): void
    {
        AdminActionLog::create([
            'admin_user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
