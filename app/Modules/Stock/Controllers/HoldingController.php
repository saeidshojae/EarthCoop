<?php
namespace App\Modules\Stock\Controllers;

use App\Modules\Stock\Services\HoldingService;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\HoldingTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class HoldingController extends Controller
{
    protected $holdingService;
    
    public function __construct(HoldingService $holdingService)
    {
        $this->holdingService = $holdingService;
    }
    
    // نمایش کیف‌سهام کاربر
    public function index()
    {
        $holdings = $this->holdingService->getUserHoldings(auth()->id());
        
        return view('Stock::holding_index', compact('holdings'));
    }
    
    // نمایش جزئیات یک سهم
    public function show($stockId)
    {
        $holding = $this->holdingService->getOrCreateHolding(auth()->id(), $stockId);
        $transactions = $holding->transactions()->latest()->paginate(20);
        
        return view('Stock::holding_show', compact('holding', 'transactions'));
    }
    
    // نمایش لیست holdings برای ادمین
    public function adminIndex(Request $request)
    {
        $query = Holding::with(['user', 'stock'])->where('quantity', '>', 0);
        
        // فیلتر بر اساس کاربر
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // فیلتر بر اساس سهام
        if ($request->filled('stock_id')) {
            $query->where('stock_id', $request->stock_id);
        }
        
        // فیلتر بر اساس تعداد
        if ($request->filled('quantity_min')) {
            $query->where('quantity', '>=', $request->quantity_min);
        }
        if ($request->filled('quantity_max')) {
            $query->where('quantity', '<=', $request->quantity_max);
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
        
        $holdings = $query->latest()->paginate(50);
        
        // محاسبه آمار
        $stats = [
            'total_holdings' => Holding::where('quantity', '>', 0)->count(),
            'total_shareholders' => Holding::where('quantity', '>', 0)->distinct('user_id')->count('user_id'),
            'total_shares' => Holding::sum('quantity'),
            'total_transactions' => HoldingTransaction::count(),
            'today_transactions' => HoldingTransaction::whereDate('created_at', today())->count(),
        ];
        
        return view('stock.admin_holdings_index', compact('holdings', 'stats'));
    }
    
    // نمایش جزئیات یک holding برای ادمین
    public function adminShow($holdingId)
    {
        $holding = Holding::with(['user', 'stock'])->findOrFail($holdingId);
        $transactions = $holding->transactions()->with('holding.user', 'holding.stock')->latest()->paginate(50);
        
        return view('stock.admin_holdings_show', compact('holding', 'transactions'));
    }
}
