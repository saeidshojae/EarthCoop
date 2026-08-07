<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\NajmBahar\Services\AccountService;
use App\Helpers\BaharMoney;

class NajmBaharController extends Controller
{
    protected $accounts;

    public function __construct(AccountService $accounts)
    {
        $this->accounts = $accounts;
    }

    public function createAccount(Request $request)
    {
        $request->validate(['user_id' => 'required|integer']);
        $acc = $this->accounts->createMainAccountForUser((int)$request->input('user_id'));
        return response()->json(['account' => $acc], 201);
    }

    public function getBalance($accountNumber)
    {
        $acc = \App\Modules\NajmBahar\Models\Account::where('account_number', $accountNumber)->first();
        if (!$acc) {
            return response()->json(['message' => 'not found'], 404);
        }
        return response()->json([
            'balance' => $acc->balance,
            'balance_bahar' => BaharMoney::formatDecimalValue($acc->balance),
            'balance_display' => BaharMoney::format($acc->balance),
        ]);
    }
}
