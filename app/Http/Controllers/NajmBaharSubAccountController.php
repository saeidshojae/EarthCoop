<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Services\AccountService;
use App\Helpers\BaharMoney;
use App\Models\Group;
use App\Models\GroupUser;
use App\Services\NajmBaharAuditLogger;
use Illuminate\Support\Facades\Auth;

class NajmBaharSubAccountController extends Controller
{
    protected $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

    /**
     * نمایش لیست حساب‌های فرعی
     */
    public function index()
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return redirect()->route('najm-bahar.dashboard')
                ->with('error', 'حساب اصلی شما یافت نشد. لطفاً ابتدا حساب خود را فعال کنید.');
        }

            $subAccounts = $this->subAccountService->getAllSubAccountsForAccount($account->id);

        return view('najm-bahar.sub-accounts.index', compact('account', 'subAccounts'));
    }

    /**
     * نمایش فرم ایجاد حساب فرعی
     */
    public function create()
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return redirect()->route('najm-bahar.dashboard')
                ->with('error', 'حساب اصلی شما یافت نشد.');
        }

        return view('najm-bahar.sub-accounts.create', compact('account'));
    }

    /**
     * ذخیره حساب فرعی جدید
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return redirect()->route('najm-bahar.dashboard')
                ->with('error', 'حساب اصلی شما یافت نشد.');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $subAccount = $this->subAccountService->createSubAccount($account->id, $validated['name'] ?? null);

            return redirect()->route('najm-bahar.sub-accounts.index')
                ->with('success', 'حساب فرعی با موفقیت ایجاد شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در ایجاد حساب فرعی: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * نمایش جزئیات حساب فرعی
     */
    public function show(SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account || $subAccount->account_id !== $account->id) {
            return redirect()->route('najm-bahar.sub-accounts.index')
                ->with('error', 'دسترسی غیرمجاز.');
        }

            $otherSubAccounts = $this->subAccountService->getSubAccountsForAccount($account->id)
            ->where('id', '!=', $subAccount->id)
            ->values();

        return view('najm-bahar.sub-accounts.show', compact('account', 'subAccount', 'otherSubAccounts'));
    }

    /**
     * بروزرسانی نام حساب فرعی
     */
    public function update(Request $request, SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $oldName = $subAccount->name;

        $subAccount->name = $validated['name'];
        $subAccount->save();

        NajmBaharAuditLogger::log([
            'actor_user_id' => $user->id,
            'action' => 'subaccount.rename',
            'account_number' => $account->account_number,
            'sub_account_code' => $subAccount->sub_account_code,
            'description' => 'ویرایش نام حساب فرعی',
            'meta' => [
                'old_name' => $oldName,
                'new_name' => $subAccount->name,
            ],
        ]);

        return back()->with('success', 'نام حساب فرعی با موفقیت بروزرسانی شد.');
    }

    /**
     * بستن حساب فرعی با انتقال موجودی به حساب اصلی یا حساب فرعی دیگر
     */
    public function closeWithTransfer(Request $request, SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'destination' => 'required|in:main,subaccount',
            'destination_sub_account_id' => 'nullable|integer|exists:najm_sub_accounts,id',
            'description' => 'nullable|string|max:500',
        ]);

        $amount = intval($subAccount->balance);

        try {
            $destinationLabel = $validated['destination'] === 'subaccount' ? 'حساب فرعی' : 'حساب اصلی';
            $destinationSubAccount = null;

            if ($amount > 0) {
                if ($validated['destination'] === 'subaccount') {
                    if (! $request->filled('destination_sub_account_id')) {
                        return back()->with('error', 'حساب فرعی مقصد را انتخاب کنید.')->withInput();
                    }

                    $destinationSubAccount = SubAccount::where('id', $validated['destination_sub_account_id'])
                        ->where('account_id', $account->id)
                        ->where('status', 1)
                        ->first();

                    if (! $destinationSubAccount || $destinationSubAccount->id === $subAccount->id) {
                        return back()->with('error', 'حساب فرعی مقصد معتبر نیست.')->withInput();
                    }

                    $this->subAccountService->transferBetweenSubAccounts(
                        $subAccount->id,
                        $destinationSubAccount->id,
                        $amount,
                        $validated['description'] ?? null
                    );
                } else {
                    $this->subAccountService->transferFromSubAccount(
                        $subAccount->id,
                        $account->id,
                        $amount,
                        $validated['description'] ?? null
                    );
                }
            }

            $this->subAccountService->deactivateSubAccount($subAccount->id);

            NajmBaharAuditLogger::log([
                'actor_user_id' => $user->id,
                'action' => 'subaccount.close',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'amount' => $amount,
                'direction' => 'close',
                'description' => $validated['description'] ?? 'بستن حساب فرعی',
                'meta' => [
                    'destination' => $validated['destination'],
                    'destination_label' => $destinationLabel,
                    'destination_sub_account_code' => $destinationSubAccount?->sub_account_code,
                    'balance_transferred' => $amount,
                ],
            ]);

            return redirect()->route('najm-bahar.sub-accounts.index')
                ->with('success', 'حساب فرعی با موفقیت بسته شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در بستن حساب فرعی: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * انتقال وجه به حساب فرعی
     */
    public function transferTo(Request $request, SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return back()->with('error', 'مبلغ باید بزرگتر از صفر باشد.');
        }

        try {
            $this->subAccountService->transferToSubAccount(
                $account->id,
                $subAccount->id,
                $amount,
                $validated['description'] ?? null
            );

            return back()->with('success', 'وجه با موفقیت به حساب فرعی منتقل شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در انتقال وجه: ' . $e->getMessage());
        }
    }

    /**
     * انتقال وجه از حساب فرعی
     */
    public function transferFrom(Request $request, SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return back()->with('error', 'مبلغ باید بزرگتر از صفر باشد.');
        }

        try {
            $this->subAccountService->transferFromSubAccount(
                $subAccount->id,
                $account->id,
                $amount,
                $validated['description'] ?? null
            );

            return back()->with('success', 'وجه با موفقیت از حساب فرعی منتقل شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در انتقال وجه: ' . $e->getMessage());
        }
    }

    /**
     * غیرفعال کردن حساب فرعی
     */
    public function deactivate(SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        try {
            $this->subAccountService->deactivateSubAccount($subAccount->id);

            NajmBaharAuditLogger::log([
                'actor_user_id' => $user->id,
                'action' => 'subaccount.deactivate',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'description' => 'غیرفعال کردن حساب فرعی',
            ]);

            return redirect()->route('najm-bahar.sub-accounts.index')
                ->with('success', 'حساب فرعی با موفقیت غیرفعال شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در غیرفعال کردن حساب فرعی: ' . $e->getMessage());
        }
    }

    public function activate(SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        try {
            $this->subAccountService->activateSubAccount($subAccount->id);

            NajmBaharAuditLogger::log([
                'actor_user_id' => $user->id,
                'action' => 'subaccount.activate',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'description' => 'فعال سازی حساب فرعی',
            ]);

            return redirect()->route('najm-bahar.sub-accounts.index')
                ->with('success', 'حساب فرعی با موفقیت فعال شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در فعال سازی حساب فرعی: ' . $e->getMessage());
        }
    }

    public function indexForGroup(Group $group)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به حساب گروه برای شما مجاز نیست.');
        }

            $subAccounts = $this->subAccountService->getAllSubAccountsForAccount($account->id);
        $routePrefix = 'groups.najm-bahar';
        $routeParams = ['group' => $group->id];
        $accountLabel = 'حساب اصلی گروه';

        return view('najm-bahar.sub-accounts.index', compact('account', 'subAccounts', 'routePrefix', 'routeParams', 'accountLabel'));
    }

    public function createForGroup(Group $group)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به حساب گروه برای شما مجاز نیست.');
        }

        $routePrefix = 'groups.najm-bahar';
        $routeParams = ['group' => $group->id];
        $accountLabel = 'حساب اصلی گروه';

        return view('najm-bahar.sub-accounts.create', compact('account', 'routePrefix', 'routeParams', 'accountLabel'));
    }

    public function storeForGroup(Request $request, Group $group)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به حساب گروه برای شما مجاز نیست.');
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $subAccount = $this->subAccountService->createSubAccount($account->id, $validated['name'] ?? null);

            NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
                'actor_role' => $this->getGroupActorRole($group),
                'action' => 'subaccount.create',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'description' => 'ایجاد حساب فرعی گروه',
                'meta' => [
                    'sub_account_name' => $subAccount->name,
                ],
            ]);

            return redirect()->route('groups.najm-bahar.sub-accounts.index', $group)
                ->with('success', 'حساب فرعی گروه با موفقیت ایجاد شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در ایجاد حساب فرعی: ' . $e->getMessage())->withInput();
        }
    }

    public function showForGroup(Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return redirect()->route('groups.najm-bahar.sub-accounts.index', $group)
                ->with('error', 'دسترسی غیرمجاز.');
        }

        $routePrefix = 'groups.najm-bahar';
        $routeParams = ['group' => $group->id];
        $accountLabel = 'حساب اصلی گروه';
        $otherSubAccounts = $this->subAccountService->getSubAccountsForAccount($account->id)
            ->where('id', '!=', $subAccount->id)
            ->values();

        return view('najm-bahar.sub-accounts.show', compact('account', 'subAccount', 'routePrefix', 'routeParams', 'accountLabel', 'otherSubAccounts'));
    }

    public function updateForGroup(Request $request, Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $oldName = $subAccount->name;
        $subAccount->name = $validated['name'];
        $subAccount->save();

        NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
            'actor_role' => $this->getGroupActorRole($group),
            'action' => 'subaccount.rename',
            'account_number' => $account->account_number,
            'sub_account_code' => $subAccount->sub_account_code,
            'description' => 'ویرایش نام حساب فرعی گروه',
            'meta' => [
                'old_name' => $oldName,
                'new_name' => $subAccount->name,
            ],
        ]);

        return back()->with('success', 'نام حساب فرعی با موفقیت بروزرسانی شد.');
    }

    public function closeWithTransferForGroup(Request $request, Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'destination' => 'required|in:main,subaccount',
            'destination_sub_account_id' => 'nullable|integer|exists:najm_sub_accounts,id',
            'description' => 'nullable|string|max:500',
        ]);

        $amount = intval($subAccount->balance);

        try {
            $destinationLabel = $validated['destination'] === 'subaccount' ? 'حساب فرعی' : 'حساب اصلی';
            $destinationSubAccount = null;

            if ($amount > 0) {
                if ($validated['destination'] === 'subaccount') {
                    if (! $request->filled('destination_sub_account_id')) {
                        return back()->with('error', 'حساب فرعی مقصد را انتخاب کنید.')->withInput();
                    }

                    $destinationSubAccount = SubAccount::where('id', $validated['destination_sub_account_id'])
                        ->where('account_id', $account->id)
                        ->where('status', 1)
                        ->first();

                    if (! $destinationSubAccount || $destinationSubAccount->id === $subAccount->id) {
                        return back()->with('error', 'حساب فرعی مقصد معتبر نیست.')->withInput();
                    }

                    $this->subAccountService->transferBetweenSubAccounts(
                        $subAccount->id,
                        $destinationSubAccount->id,
                        $amount,
                        $validated['description'] ?? null
                    );
                } else {
                    $this->subAccountService->transferFromSubAccount(
                        $subAccount->id,
                        $account->id,
                        $amount,
                        $validated['description'] ?? null
                    );
                }
            }

            $this->subAccountService->deactivateSubAccount($subAccount->id);

            NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
                'actor_role' => $this->getGroupActorRole($group),
                'action' => 'subaccount.close',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'amount' => $amount,
                'direction' => 'close',
                'description' => $validated['description'] ?? 'بستن حساب فرعی گروه',
                'meta' => [
                    'destination' => $validated['destination'],
                    'destination_label' => $destinationLabel,
                    'destination_sub_account_code' => $destinationSubAccount?->sub_account_code,
                    'balance_transferred' => $amount,
                ],
            ]);

            return redirect()->route('groups.najm-bahar.sub-accounts.index', $group)
                ->with('success', 'حساب فرعی با موفقیت بسته شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در بستن حساب فرعی: ' . $e->getMessage())->withInput();
        }
    }

    public function transferToForGroup(Request $request, Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return back()->with('error', 'مبلغ باید بزرگتر از صفر باشد.');
        }

        try {
            $this->subAccountService->transferToSubAccount(
                $account->id,
                $subAccount->id,
                $amount,
                $validated['description'] ?? null
            );

            NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
                'actor_role' => $this->getGroupActorRole($group),
                'action' => 'subaccount.transfer_to',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'amount' => $amount,
                'direction' => 'to_subaccount',
                'description' => $validated['description'] ?? 'انتقال به حساب فرعی',
                'meta' => [
                    'from_account' => $account->account_number,
                    'to_sub_account' => $subAccount->sub_account_code,
                ],
            ]);

            return back()->with('success', 'وجه با موفقیت به حساب فرعی منتقل شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در انتقال وجه: ' . $e->getMessage());
        }
    }

    public function transferFromForGroup(Request $request, Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return back()->with('error', 'مبلغ باید بزرگتر از صفر باشد.');
        }

        try {
            $this->subAccountService->transferFromSubAccount(
                $subAccount->id,
                $account->id,
                $amount,
                $validated['description'] ?? null
            );

            NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
                'actor_role' => $this->getGroupActorRole($group),
                'action' => 'subaccount.transfer_from',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'amount' => $amount,
                'direction' => 'from_subaccount',
                'description' => $validated['description'] ?? 'انتقال از حساب فرعی',
                'meta' => [
                    'from_sub_account' => $subAccount->sub_account_code,
                    'to_account' => $account->account_number,
                ],
            ]);

            return back()->with('success', 'وجه با موفقیت از حساب فرعی منتقل شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در انتقال وجه: ' . $e->getMessage());
        }
    }

    public function deactivateForGroup(Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        try {
            $this->subAccountService->deactivateSubAccount($subAccount->id);

            NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
                'actor_role' => $this->getGroupActorRole($group),
                'action' => 'subaccount.deactivate',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'description' => 'غیرفعال کردن حساب فرعی',
            ]);

            return redirect()->route('groups.najm-bahar.sub-accounts.index', $group)
                ->with('success', 'حساب فرعی با موفقیت غیرفعال شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در غیرفعال کردن حساب فرعی: ' . $e->getMessage());
        }
    }

    public function activateForGroup(Group $group, SubAccount $subAccount)
    {
        $account = $this->getGroupAccountOrRedirect($group);
        if (! $account || $subAccount->account_id !== $account->id) {
            return back()->with('error', 'دسترسی غیرمجاز.');
        }

        try {
            $this->subAccountService->activateSubAccount($subAccount->id);

            NajmBaharAuditLogger::logGroupAction($group, Auth::user(), [
                'actor_role' => $this->getGroupActorRole($group),
                'action' => 'subaccount.activate',
                'account_number' => $account->account_number,
                'sub_account_code' => $subAccount->sub_account_code,
                'description' => 'فعال سازی حساب فرعی',
            ]);

            return redirect()->route('groups.najm-bahar.sub-accounts.index', $group)
                ->with('success', 'حساب فرعی با موفقیت فعال شد.');
        } catch (\Exception $e) {
            return back()->with('error', 'خطا در فعال سازی حساب فرعی: ' . $e->getMessage());
        }
    }

    private function getGroupAccountOrRedirect(Group $group): ?Account
    {
        $user = Auth::user();
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

        $accountService = app(AccountService::class);
        return $accountService->ensureLegalEntityAccountForGroup($group);
    }

    private function getGroupActorRole(Group $group): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return GroupUser::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->value('role');
    }
}

