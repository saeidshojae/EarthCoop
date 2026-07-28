<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            // Group notifications
            $table->boolean('group_post')->default(1);
            $table->boolean('group_poll')->default(1);
            $table->boolean('group_comment_new')->default(1);
            $table->boolean('group_comment_reply')->default(1);
            $table->boolean('group_invitation')->default(1);
            $table->boolean('group_report_message')->default(1);
            $table->boolean('group_chat_request')->default(1);

            // Election notifications
            $table->boolean('election_started')->default(1);
            $table->boolean('election_finished')->default(1);
            $table->boolean('election_elected')->default(1);
            $table->boolean('election_accepted')->default(1);
            $table->boolean('election_reminder')->default(1);

            // Chat notifications
            $table->boolean('chat_message')->default(1);
            $table->boolean('chat_reply')->default(1);
            $table->boolean('chat_mention')->default(1);

            // Auction notifications
            $table->boolean('auction_started')->default(1);
            $table->boolean('auction_ended')->default(1);
            $table->boolean('auction_bid')->default(1);
            $table->boolean('auction_won')->default(1);
            $table->boolean('auction_outbid')->default(1);
            $table->boolean('auction_lost')->default(1);
            $table->boolean('auction_cancelled')->default(1);
            $table->boolean('auction_reminder')->default(1);

            // Wallet notifications
            $table->boolean('wallet_settled')->default(1);
            $table->boolean('wallet_released')->default(1);
            $table->boolean('wallet_held')->default(1);
            $table->boolean('wallet_credited')->default(1);
            $table->boolean('wallet_debited')->default(1);

            // Shares notifications
            $table->boolean('shares_received')->default(1);
            $table->boolean('shares_gifted')->default(1);
            $table->boolean('stock_price_changed')->default(1);

            // Najm Bahar notifications
            $table->boolean('najm_bahar_transaction')->default(true);
            $table->boolean('najm_bahar_low_balance')->default(1);
            $table->boolean('najm_bahar_large_transaction')->default(1);
            $table->boolean('najm_bahar_scheduled_transaction')->default(1);
            $table->integer('najm_bahar_low_balance_threshold')->nullable();
            $table->integer('najm_bahar_large_transaction_threshold')->nullable();

            // Admin and general
            $table->boolean('admin_message')->default(1);
            $table->boolean('email_notifications')->default(0);
            $table->boolean('push_notifications')->default(1);

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};