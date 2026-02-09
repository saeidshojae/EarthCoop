<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Http\Request;

class NajmBaharAccountsController extends Controller
{
    public function index(Request $request)
    {
        $accountsQuery = Account::query();

        if ($request->filled('type')) {
            $accountsQuery->where('type', $request->type);
        }

        if ($request->filled('account_number')) {
            $accountsQuery->where('account_number', 'like', '%' . $request->account_number . '%');
        }

        if ($request->filled('user_id')) {
            $accountsQuery->where('user_id', $request->user_id);
        }

        $accounts = $accountsQuery
            ->orderBy('account_number')
            ->paginate(50)
            ->appends($request->query());

        $subAccounts = SubAccount::with('account')
            ->orderBy('sub_account_code')
            ->get();

        return view('admin.najm-bahar.accounts.index', compact('accounts', 'subAccounts'));
    }

    public function transactions(string $accountNumber, AccountService $accountService)
    {
        $account = Account::where('account_number', $accountNumber)->first();

        if (! $account) {
            $subAccount = SubAccount::where('sub_account_code', $accountNumber)->first();
            if (! $subAccount) {
                abort(404);
            }
            $account = $accountService->ensureSubAccountAccount($subAccount);
        }

        $transactions = Transaction::with(['fromAccount', 'toAccount'])
            ->where('from_account_id', $account->id)
            ->orWhere('to_account_id', $account->id)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.najm-bahar.accounts.transactions', compact('account', 'transactions'));
    }
}
