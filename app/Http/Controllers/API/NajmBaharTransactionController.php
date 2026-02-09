<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\NajmBahar\Services\TransactionService;
use App\Modules\NajmBahar\Services\AccountNumberService;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ScheduledTransaction;
use App\Helpers\BaharMoney;
use Illuminate\Support\Facades\Auth;

class NajmBaharTransactionController extends Controller
{
    protected $txService;

    public function __construct(TransactionService $txService)
    {
        $this->txService = $txService;
    }

    // create immediate transfer
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'to_account_number' => 'required|string',
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'idempotency_key' => 'nullable|string',
        ]);

        $amount = BaharMoney::parseToGol($data['amount']);
        if ($amount <= 0) {
            return response()->json(['message' => 'amount must be greater than zero'], 422);
        }

        $fromAccountNumber = AccountNumberService::makeMainAccountNumberForUser(Auth::id());

        // verify caller owns from account
        $fromAcc = Account::where('account_number', $fromAccountNumber)->first();
        if (!$fromAcc || $fromAcc->user_id != Auth::id()) {
            return response()->json(['message' => 'source account not found or unauthorized'], 403);
        }

        // idempotency: if provided and exists, return existing transaction
        if (!empty($data['idempotency_key'])) {
            $existing = \App\Modules\NajmBahar\Models\Transaction::where('metadata->idempotency_key', $data['idempotency_key'])->first();
            if ($existing) return response()->json(['transaction' => $existing], 200);
        }

        try {
            $tx = $this->txService->transfer($fromAccountNumber, $data['to_account_number'], $amount, $request->input('description'), [], $data['idempotency_key'] ?? null);
            return response()->json(['transaction' => $tx], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // schedule a transaction
    public function schedule(Request $request)
    {
        $data = $request->validate([
            'to_account_number' => 'required|string',
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'execute_at' => 'required|date',
        ]);

        $amount = BaharMoney::parseToGol($data['amount']);
        if ($amount <= 0) {
            return response()->json(['message' => 'amount must be greater than zero'], 422);
        }

        $fromAccountNumber = AccountNumberService::makeMainAccountNumberForUser(Auth::id());
        $fromAcc = Account::where('account_number', $fromAccountNumber)->first();
        if (!$fromAcc || $fromAcc->user_id != Auth::id()) {
            return response()->json(['message' => 'source account not found or unauthorized'], 403);
        }

        // create a pending transaction record
        $tx = \App\Modules\NajmBahar\Models\Transaction::create([
            'from_account_id' => $fromAcc->id,
            'to_account_id' => null,
            'amount' => $amount,
            'type' => 'scheduled',
            'status' => 'pending',
            'scheduled_at' => $data['execute_at'],
            'metadata' => ['to_account_number' => $data['to_account_number']],
            'description' => $request->input('description'),
        ]);

        ScheduledTransaction::create([
            'transaction_id' => $tx->id,
            'execute_at' => $data['execute_at'],
            'status' => 'scheduled',
            'payload' => ['to_account_number' => $data['to_account_number']],
        ]);

        return response()->json(['scheduled' => true, 'transaction' => $tx], 201);
    }

    // list transactions for caller
    public function index(Request $request)
    {
        $accountNumber = AccountNumberService::makeMainAccountNumberForUser(Auth::id());
        $acc = Account::where('account_number', $accountNumber)->first();
        if (!$acc) return response()->json(['data' => []]);

        $txs = \App\Modules\NajmBahar\Models\Transaction::where(function ($q) use ($acc) {
            $q->where('from_account_id', $acc->id)->orWhere('to_account_id', $acc->id);
        })->orderBy('created_at', 'desc')->paginate(25);

        return response()->json($txs);
    }

    // ledger entries for account
    public function ledger($accountNumber)
    {
        $acc = Account::where('account_number', $accountNumber)->first();
        if (!$acc) return response()->json(['message' => 'not found'], 404);

        $entries = \App\Modules\NajmBahar\Models\LedgerEntry::where('account_id', $acc->id)->orderBy('created_at', 'desc')->paginate(50);
        return response()->json($entries);
    }
}
