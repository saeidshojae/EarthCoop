<?php

namespace App\Modules\Stock\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use Illuminate\Http\Request;

final class ExternalCapitalOperationsController extends Controller
{
    public function index(Request $request)
    {
        $status = trim((string) $request->query('status', ''));
        $currency = strtoupper(trim((string) $request->query('currency', '')));

        $query = ExternalPaymentIntent::query()
            ->withCount('reconciliations')
            ->latest('id');

        if (in_array($status, [
            ExternalPaymentIntent::CREATED,
            ExternalPaymentIntent::PENDING,
            ExternalPaymentIntent::CONFIRMED,
            ExternalPaymentIntent::FAILED,
            ExternalPaymentIntent::CANCELLED,
            ExternalPaymentIntent::REFUNDED,
            ExternalPaymentIntent::REVERSED,
        ], true)) {
            $query->where('status', $status);
        }

        if (in_array($currency, ['IRR', 'USD'], true)) {
            $query->where('currency', $currency);
        }

        return view('Stock::admin_external_payments_index', [
            'intents' => $query->paginate(30)->withQueryString(),
            'selectedStatus' => $status,
            'selectedCurrency' => $currency,
        ]);
    }

    public function show(ExternalPaymentIntent $paymentIntent)
    {
        $paymentIntent->load(['reconciliations' => fn ($query) => $query->orderBy('occurred_at')->orderBy('id')]);

        return view('Stock::admin_external_payments_show', [
            'intent' => $paymentIntent,
        ]);
    }
}
