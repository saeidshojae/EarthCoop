<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationSettingsController extends Controller
{
    /**
     * نمایش صفحه تنظیمات اعلان‌ها
     */
    public function index()
    {
        $user = Auth::user();
        $settings = NotificationSetting::forUser($user->id);

        return view('notifications.settings', compact('settings'));
    }

    /**
     * به‌روزرسانی تنظیمات اعلان‌ها
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            // Group notifications
            'group_post' => 'boolean',
            'group_poll' => 'boolean',
            'group_comment_new' => 'boolean',
            'group_comment_reply' => 'boolean',
            'group_invitation' => 'boolean',
            'group_report_message' => 'boolean',
            'group_chat_request' => 'boolean',
            // Election notifications
            'election_started' => 'boolean',
            'election_finished' => 'boolean',
            'election_elected' => 'boolean',
            'election_accepted' => 'boolean',
            'election_reminder' => 'boolean',
            // Chat notifications
            'chat_message' => 'boolean',
            'chat_reply' => 'boolean',
            'chat_mention' => 'boolean',
            // Auction notifications
            'auction_started' => 'boolean',
            'auction_ended' => 'boolean',
            'auction_bid' => 'boolean',
            'auction_won' => 'boolean',
            'auction_outbid' => 'boolean',
            'auction_lost' => 'boolean',
            'auction_cancelled' => 'boolean',
            'auction_reminder' => 'boolean',
            // Wallet notifications
            'wallet_settled' => 'boolean',
            'wallet_released' => 'boolean',
            'wallet_held' => 'boolean',
            'wallet_credited' => 'boolean',
            'wallet_debited' => 'boolean',
            // Shares notifications
            'shares_received' => 'boolean',
            'shares_gifted' => 'boolean',
            // Stock notifications
            'stock_price_changed' => 'boolean',
            // Najm Bahar notifications
            'najm_bahar_transaction' => 'boolean',
            'najm_bahar_low_balance' => 'boolean',
            'najm_bahar_large_transaction' => 'boolean',
            'najm_bahar_scheduled_transaction' => 'boolean',
            'najm_bahar_low_balance_threshold' => 'integer|nullable',
            'najm_bahar_large_transaction_threshold' => 'integer|nullable',
            // Admin notifications
            'admin_message' => 'boolean',
            // General settings
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
        ]);

        // Convert checkbox values (null to false)
        $defaults = NotificationSetting::getDefaults();
        foreach ($defaults as $key => $defaultValue) {
            if (!isset($validated[$key])) {
                $validated[$key] = false;
            }
        }

        $settings = NotificationSetting::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return redirect()->route('notifications.settings')
            ->with('success', 'تنظیمات اعلان‌ها با موفقیت به‌روزرسانی شد.');
    }
}
