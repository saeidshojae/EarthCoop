<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupUser;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use App\Modules\NajmBahar\Services\AccountService;

class NajmBaharGroupController extends Controller
{
    public function dashboard(Group $group, AccountService $accountService)
    {
        $account = $this->getGroupAccountOrNull($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به داشبورد مالی گروه برای شما مجاز نیست.');
        }

        $subAccountCount = SubAccount::where('account_id', $account->id)->where('status', 1)->count();
        $transactionCount = NajmTransaction::where(function ($query) use ($account) {
            $query->where('from_account_id', $account->id)
                ->orWhere('to_account_id', $account->id);
        })->count();

        return view('najm-bahar.group-dashboard', compact('group', 'account', 'subAccountCount', 'transactionCount'));
    }

    public function wallet(Group $group, AccountService $accountService)
    {
        $account = $this->getGroupAccountOrNull($group, $accountService);
        if (! $account) {
            return redirect()->route('groups.show', $group)
                ->with('error', 'دسترسی به کیف پول گروه برای شما مجاز نیست.');
        }

        $recentTransactions = NajmTransaction::where(function ($query) use ($account) {
            $query->where('from_account_id', $account->id)
                ->orWhere('to_account_id', $account->id);
        })->orderByDesc('created_at')->limit(10)->get();

        $routePrefix = 'groups.najm-bahar';
        $routeParams = ['group' => $group->id];
        $walletOwnerLabel = 'گروه ' . $group->name;

        return view('najm-bahar.wallet', compact('account', 'recentTransactions', 'routePrefix', 'routeParams', 'walletOwnerLabel'));
    }

    private function getGroupAccountOrNull(Group $group, AccountService $accountService): ?Account
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
}
