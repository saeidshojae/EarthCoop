<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\MessageCreated::class => [
            \App\Listeners\SendGroupMessageNotifications::class,
        ],
        \App\Events\UserMentioned::class => [
            \App\Listeners\SendMentionNotification::class,
        ],
        \App\Events\BlogCreated::class => [
            \App\Listeners\SendBlogCreatedNotifications::class,
        ],
        \App\Events\PollCreated::class => [
            \App\Listeners\SendPollCreatedNotifications::class,
        ],
        \App\Events\ElectionStarted::class => [
            \App\Listeners\SendElectionStartedNotifications::class,
        ],
        \App\Events\ElectionFinished::class => [
            \App\Listeners\SendElectionFinishedNotifications::class,
        ],
        \App\Events\CandidateAccepted::class => [
            \App\Listeners\SendCandidateAcceptedNotifications::class,
        ],
        \App\Events\CommentCreated::class => [
            \App\Listeners\SendCommentCreatedNotifications::class,
        ],
               \App\Events\GroupInvitation::class => [
                   \App\Listeners\SendGroupInvitationNotifications::class,
               ],
               \App\Events\MessageReported::class => [
                   \App\Listeners\SendMessageReportedNotifications::class,
               ],
               \App\Events\ChatRequestToGroup::class => [
                   \App\Listeners\SendChatRequestToGroupNotifications::class,
               ],
        \App\Events\BidLost::class => [
            \App\Listeners\SendBidLostNotifications::class,
        ],
        \App\Events\BidCancelled::class => [
            \App\Listeners\SendBidCancelledNotifications::class,
        ],
        \App\Events\WalletSettled::class => [
            \App\Listeners\SendWalletSettledNotifications::class,
        ],
        \App\Events\WalletReleased::class => [
            \App\Listeners\SendWalletReleasedNotifications::class,
        ],
        \App\Events\WalletHeld::class => [
            \App\Listeners\SendWalletHeldNotifications::class,
        ],
        \App\Events\SharesReceived::class => [
            \App\Listeners\SendSharesReceivedNotifications::class,
        ],
        \App\Events\SharesGifted::class => [
            \App\Listeners\SendSharesGiftedNotifications::class,
        ],
        \App\Events\StockPriceChanged::class => [
            \App\Listeners\SendStockPriceChangedNotifications::class,
        ],
        \App\Events\AuctionReminder::class => [
            \App\Listeners\SendAuctionReminderNotifications::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
