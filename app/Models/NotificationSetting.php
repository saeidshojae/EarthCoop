<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        // Group notifications
        'group_post',
        'group_poll',
        'group_comment_new',
        'group_comment_reply',
        'group_invitation',
        'group_report_message',
        'group_chat_request',
        // Election notifications
        'election_started',
        'election_finished',
        'election_elected',
        'election_accepted',
        'election_reminder',
        // Chat notifications
        'chat_message',
        'chat_reply',
        'chat_mention',
        // Auction notifications
        'auction_started',
        'auction_ended',
        'auction_bid',
        'auction_won',
        'auction_outbid',
        'auction_lost',
        'auction_cancelled',
        'auction_reminder',
        // Wallet notifications
        'wallet_settled',
        'wallet_released',
        'wallet_held',
        'wallet_credited',
        'wallet_debited',
        // Shares notifications
        'shares_received',
        'shares_gifted',
        // Stock notifications
        'stock_price_changed',
        // Najm Bahar notifications
        'najm_bahar_transaction',
        'najm_bahar_low_balance',
        'najm_bahar_large_transaction',
        'najm_bahar_scheduled_transaction',
        'najm_bahar_low_balance_threshold',
        'najm_bahar_large_transaction_threshold',
        // Admin notifications
        'admin_message',
        // General settings
        'email_notifications',
        'push_notifications',
    ];

    protected $casts = [
        'group_post' => 'boolean',
        'group_poll' => 'boolean',
        'group_comment_new' => 'boolean',
        'group_comment_reply' => 'boolean',
        'group_invitation' => 'boolean',
        'group_report_message' => 'boolean',
        'group_chat_request' => 'boolean',
        'election_started' => 'boolean',
        'election_finished' => 'boolean',
        'election_elected' => 'boolean',
        'election_accepted' => 'boolean',
        'election_reminder' => 'boolean',
        'chat_message' => 'boolean',
        'chat_reply' => 'boolean',
        'chat_mention' => 'boolean',
        'auction_started' => 'boolean',
        'auction_ended' => 'boolean',
        'auction_bid' => 'boolean',
        'auction_won' => 'boolean',
        'auction_outbid' => 'boolean',
        'auction_lost' => 'boolean',
        'auction_cancelled' => 'boolean',
        'auction_reminder' => 'boolean',
        'wallet_settled' => 'boolean',
        'wallet_released' => 'boolean',
        'wallet_held' => 'boolean',
        'wallet_credited' => 'boolean',
        'wallet_debited' => 'boolean',
        'shares_received' => 'boolean',
        'shares_gifted' => 'boolean',
        'stock_price_changed' => 'boolean',
        'najm_bahar_transaction' => 'boolean',
        'najm_bahar_low_balance' => 'boolean',
        'najm_bahar_large_transaction' => 'boolean',
        'najm_bahar_scheduled_transaction' => 'boolean',
        'najm_bahar_low_balance_threshold' => 'integer',
        'najm_bahar_large_transaction_threshold' => 'integer',
        'admin_message' => 'boolean',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
    ];

    /**
     * Get the user that owns the notification settings
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get default settings for a user
     */
    public static function getDefaults(): array
    {
        return [
            'group_post' => true,
            'group_poll' => true,
            'group_comment_new' => true,
            'group_comment_reply' => true,
            'group_invitation' => true,
            'group_report_message' => true, // برای مدیران و بازرسان
            'group_chat_request' => true, // برای مدیران
            'election_started' => true,
            'election_finished' => true,
            'election_elected' => true,
            'election_accepted' => true,
            'election_reminder' => true,
            'chat_message' => true,
            'chat_reply' => true,
            'chat_mention' => true,
            'auction_started' => true,
            'auction_ended' => true,
            'auction_bid' => false, // فقط برای ادمین‌ها
            'auction_won' => true,
            'auction_outbid' => true,
            'auction_lost' => true,
            'auction_cancelled' => true,
            'auction_reminder' => true,
            'wallet_settled' => true,
            'wallet_released' => true,
            'wallet_held' => true,
            'wallet_credited' => true,
            'wallet_debited' => true,
            'shares_received' => true,
            'shares_gifted' => true,
            'stock_price_changed' => true,
            'najm_bahar_transaction' => true,
            'najm_bahar_low_balance' => true,
            'najm_bahar_large_transaction' => true,
            'najm_bahar_scheduled_transaction' => true,
            'najm_bahar_low_balance_threshold' => 1000,
            'najm_bahar_large_transaction_threshold' => 100000,
            'admin_message' => true,
            'email_notifications' => false,
            'push_notifications' => true,
        ];
    }

    /**
     * Check if a specific notification type is enabled
     */
    public function isEnabled(string $type): bool
    {
        // Map notification types to database columns
        $typeMap = [
            'group.post' => 'group_post',
            'group.poll' => 'group_poll',
            'group.comment.new' => 'group_comment_new',
            'group.comment.reply' => 'group_comment_reply',
            'group.invitation' => 'group_invitation',
            'group.report.message' => 'group_report_message',
            'group.chat.request' => 'group_chat_request',
            'group.election.started' => 'election_started',
            'group.election.finished' => 'election_finished',
            'group.election.elected' => 'election_elected',
            'group.election.accepted' => 'election_accepted',
            'group.election.reminder' => 'election_reminder',
            'chat.message' => 'chat_message',
            'chat.reply' => 'chat_reply',
            'chat.mention' => 'chat_mention',
            'auction.started' => 'auction_started',
            'auction.ended' => 'auction_ended',
            'auction.bid' => 'auction_bid',
            'auction.won' => 'auction_won',
            'auction.outbid' => 'auction_outbid',
            'auction.lost' => 'auction_lost',
            'auction.cancelled' => 'auction_cancelled',
            'auction.reminder' => 'auction_reminder',
            'wallet.settled' => 'wallet_settled',
            'wallet.released' => 'wallet_released',
            'wallet.held' => 'wallet_held',
            'wallet.credited' => 'wallet_credited',
            'wallet.debited' => 'wallet_debited',
            'shares.received' => 'shares_received',
            'shares.gifted' => 'shares_gifted',
            'stock.price_changed' => 'stock_price_changed',
            'najm-bahar.transaction' => 'najm_bahar_transaction',
            'najm-bahar.low-balance' => 'najm_bahar_low_balance',
            'najm-bahar.large-transaction' => 'najm_bahar_large_transaction',
            'najm-bahar.scheduled-executed' => 'najm_bahar_scheduled_transaction',
            'admin.message' => 'admin_message',
        ];

        $column = $typeMap[$type] ?? null;
        if (!$column) {
            return true; // Default to enabled if type not found
        }

        return (bool) $this->$column;
    }

    /**
     * Get or create settings for a user
     */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            static::getDefaults()
        );
    }
}
