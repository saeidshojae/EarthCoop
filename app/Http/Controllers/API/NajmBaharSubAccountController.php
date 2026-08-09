<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\SubAccount;
use App\Modules\NajmBahar\Services\SubAccountService;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Helpers\BaharMoney;
use Illuminate\Support\Facades\Auth;

class NajmBaharSubAccountController extends Controller
{
    protected $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

    /**
     * دریافت لیست حساب‌های فرعی
     */
    public function index()
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $subAccounts = $this->subAccountService->getSubAccountsForAccount($account->id);

        return response()->json([
            'data' => $subAccounts->map(function($subAccount) {
                return [
                    'id' => $subAccount->id,
                    'sub_account_code' => $subAccount->sub_account_code,
                    'name' => $subAccount->name,
                    'balance' => $subAccount->balance,
                    'status' => $subAccount->status,
                    'created_at' => $subAccount->created_at,
                ];
            }),
        ]);
    }

    /**
     * ایجاد حساب فرعی جدید
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $subAccount = $this->subAccountService->createSubAccount($account->id, $validated['name'] ?? null);

            return response()->json([
                'message' => 'Sub-account created successfully',
                'data' => [
                    'id' => $subAccount->id,
                    'sub_account_code' => $subAccount->sub_account_code,
                    'name' => $subAccount->name,
                    'balance' => $subAccount->balance,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * دریافت جزئیات حساب فرعی
     */
    public function show(SubAccount $subAccount)
    {
        $user = Auth::user();
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser($user->id);
        $account = Account::where('account_number', $accountNumber)->first();

        if (!$account || $subAccount->account_id !== $account->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $subAccount->id,
                'sub_account_code' => $subAccount->sub_account_code,
                'name' => $subAccount->name,
                'balance' => $subAccount->balance,
                'status' => $subAccount->status,
                'meta' => $subAccount->meta,
                'created_at' => $subAccount->created_at,
            ],
        ]);
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
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return response()->json(['message' => 'amount must be greater than zero'], 422);
        }

        try {
            $this->subAccountService->transferToSubAccount(
                $account->id,
                $subAccount->id,
                $amount,
                $validated['description'] ?? null
            );

            $account->refresh();
            $subAccount->refresh();

            return response()->json([
                'message' => 'Transfer successful',
                'data' => [
                    'main_account_balance' => $account->balance,
                    'sub_account_balance' => $subAccount->balance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'description' => 'nullable|string|max:500',
        ]);

        $amount = BaharMoney::parseToGol($validated['amount']);
        if ($amount <= 0) {
            return response()->json(['message' => 'amount must be greater than zero'], 422);
        }

        try {
            $this->subAccountService->transferFromSubAccount(
                $subAccount->id,
                $account->id,
                $amount,
                $validated['description'] ?? null
            );

            $account->refresh();
            $subAccount->refresh();

            return response()->json([
                'message' => 'Transfer successful',
                'data' => [
                    'main_account_balance' => $account->balance,
                    'sub_account_balance' => $subAccount->balance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}

