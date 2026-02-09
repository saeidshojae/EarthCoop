<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Services\WalletService;
use App\Modules\Stock\Models\Wallet;
use App\Modules\Stock\Models\WalletTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WalletController extends Controller
{
    protected $walletService;
    
    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }
    
    // نمایش کیف‌پول کاربر
    public function index()
    {
        $wallet = $this->walletService->getOrCreateWallet(auth()->id());
        $transactions = $wallet->transactions()->latest()->paginate(20);
        
        return view('Stock::wallet_index', compact('wallet', 'transactions'));
    }
    
    // نمایش لیست کیف‌پول‌ها برای ادمین
    public function adminIndex(Request $request)
    {
        $query = Wallet::with('user');
        
        // فیلتر بر اساس کاربر
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // فیلتر بر اساس موجودی
        if ($request->filled('balance_min')) {
            $query->where('balance', '>=', $request->balance_min);
        }
        if ($request->filled('balance_max')) {
            $query->where('balance', '<=', $request->balance_max);
        }
        
        // جستجو
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $wallets = $query->latest()->paginate(50);
        
        // محاسبه آمار
        $stats = [
            'total_wallets' => Wallet::count(),
            'total_balance' => Wallet::sum('balance'),
            'total_held' => Wallet::sum('held_amount'),
            'total_available' => Wallet::sum('balance') - Wallet::sum('held_amount'),
            'total_transactions' => WalletTransaction::count(),
            'today_transactions' => WalletTransaction::whereDate('created_at', today())->count(),
        ];
        
        return view('stock.admin_wallet_index', compact('wallets', 'stats'));
    }
    
    // نمایش جزئیات یک کیف‌پول برای ادمین
    public function adminShow($walletId)
    {
        $wallet = Wallet::with('user')->findOrFail($walletId);
        $transactions = $wallet->transactions()->with('wallet.user')->latest()->paginate(50);
        
        return view('stock.admin_wallet_show', compact('wallet', 'transactions'));
    }
    
    // شارژ دستی کیف‌پول (ادمین)
    public function adminCredit(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        
        $wallet = $this->walletService->getOrCreateWallet($data['user_id']);
        $this->walletService->credit($wallet, $data['amount'], $data['description']);
        
        // Dispatch event
        $user = \App\Models\User::find($data['user_id']);
        if ($user) {
            event(new \App\Events\WalletSettled($wallet, $user, $data['amount'], null, $data['description'] ?? 'شارژ توسط ادمین'));
        }
        
        return back()->with('success', 'کیف‌پول با موفقیت شارژ شد');
    }
    
    // کسر از کیف‌پول (ادمین)
    public function adminDebit(Request $request)
    {
        $data = $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        
        $wallet = Wallet::findOrFail($data['wallet_id']);
        
        if ($wallet->balance < $data['amount']) {
            return back()->with('error', 'موجودی کیف‌پول کافی نیست');
        }
        
        $this->walletService->debit($wallet, $data['amount'], $data['description']);
        
        // Dispatch event
        $user = $wallet->user;
        if ($user) {
            event(new \App\Events\WalletSettled($wallet, $user, $data['amount'], null, $data['description'] ?? 'کسر توسط ادمین'));
        }
        
        return back()->with('success', 'مبلغ از کیف‌پول کسر شد');
    }
}
