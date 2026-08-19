<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Blog;
use App\Models\ExperienceField;
use App\Models\FaqQuestion;
use App\Models\KbArticle;
use App\Models\OccupationalField;
use App\Models\Page;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account as NajmBaharAccount;
use App\Modules\NajmBahar\Models\Fee as NajmBaharFee;
use App\Modules\NajmBahar\Models\Investment as NajmBaharInvestment;
use App\Modules\NajmBahar\Models\LedgerEntry as NajmBaharLedgerEntry;
use App\Modules\NajmBahar\Models\Project as NajmBaharProject;
use App\Modules\NajmBahar\Models\ProjectCategory as NajmBaharProjectCategory;
use App\Modules\NajmBahar\Models\ProjectReview as NajmBaharProjectReview;
use App\Modules\NajmBahar\Models\SalaryRule as NajmBaharSalaryRule;
use App\Modules\NajmBahar\Models\SalaryRun as NajmBaharSalaryRun;
use App\Modules\NajmBahar\Models\SalaryRunItem as NajmBaharSalaryRunItem;
use App\Modules\NajmBahar\Models\ScheduledTransaction as NajmBaharScheduledTransaction;
use App\Modules\NajmBahar\Models\SubAccount as NajmBaharSubAccount;
use App\Modules\NajmBahar\Models\Transaction as NajmBaharTransaction;
use App\Observers\NajmHoda\ContentModelObserver;
use App\Observers\NajmHoda\FounderReferenceDataObserver;
use App\Observers\NajmHoda\FounderUserObserver;
use App\Observers\NajmHoda\NajmBaharGenericModelObserver;
use App\Observers\NajmHoda\NajmBaharInvestmentObserver;
use App\Observers\NajmHoda\NajmBaharScheduledTransactionObserver;
use App\Observers\NajmHoda\NajmBaharTransactionObserver;
use App\Observers\NajmHoda\TicketCommentObserver;
use App\Observers\NajmHoda\TicketObserver;
use Illuminate\Auth\Events\Failed as AuthFailed;
use Illuminate\Auth\Events\Login as AuthLogin;
use Illuminate\Auth\Events\Logout as AuthLogout;
use Illuminate\Auth\Events\PasswordReset as AuthPasswordReset;
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
            \App\Listeners\CaptureNajmHodaAuthLifecycle::class,
        ],
        AuthLogin::class => [
            \App\Listeners\CaptureNajmHodaAuthLifecycle::class,
        ],
        AuthFailed::class => [
            \App\Listeners\CaptureNajmHodaAuthLifecycle::class,
        ],
        AuthLogout::class => [
            \App\Listeners\CaptureNajmHodaAuthLifecycle::class,
        ],
        AuthPasswordReset::class => [
            \App\Listeners\CaptureNajmHodaAuthLifecycle::class,
        ],
        \App\Events\MessageCreated::class => [
            \App\Listeners\SendGroupMessageNotifications::class,
            \App\Listeners\HandleNajmHodaGroupMessage::class,
            \App\Listeners\CaptureNajmHodaRuntimeInput::class,
        ],
        \App\Events\GroupPollUpdated::class => [
            \App\Listeners\CaptureNajmHodaRuntimeInput::class,
        ],
        \App\Events\GroupFeedUpdated::class => [
            \App\Listeners\CaptureNajmHodaRuntimeInput::class,
        ],
        \App\Events\UserMentioned::class => [
            \App\Listeners\SendMentionNotification::class,
        ],
        \App\Events\BlogCreated::class => [
            \App\Listeners\SendBlogCreatedNotifications::class,
            \App\Listeners\BridgeSystemGroupArtifactToRealtimeFeed::class,
        ],
        \App\Events\PollCreated::class => [
            \App\Listeners\SendPollCreatedNotifications::class,
            \App\Listeners\BridgeSystemGroupArtifactToRealtimeFeed::class,
        ],
        \App\Events\ElectionStarted::class => [
            \App\Listeners\SendElectionStartedNotifications::class,
            \App\Listeners\CaptureNajmHodaRuntimeInput::class,
        ],
        \App\Events\ElectionFinished::class => [
            \App\Listeners\SendElectionFinishedNotifications::class,
            \App\Listeners\CaptureNajmHodaRuntimeInput::class,
        ],
        \App\Events\CandidateAccepted::class => [
            \App\Listeners\SendCandidateAcceptedNotifications::class,
        ],
        \App\Events\CommentCreated::class => [
            \App\Listeners\SendCommentCreatedNotifications::class,
            \App\Listeners\BridgeSystemGroupArtifactToRealtimeFeed::class,
        ],
        \App\Events\GroupInvitation::class => [
            \App\Listeners\SendGroupInvitationNotifications::class,
        ],
        \App\Events\MessageReported::class => [
            \App\Listeners\SendMessageReportedNotifications::class,
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
        User::observe(FounderUserObserver::class);
        ExperienceField::observe(FounderReferenceDataObserver::class);
        OccupationalField::observe(FounderReferenceDataObserver::class);
        Ticket::observe(TicketObserver::class);
        TicketComment::observe(TicketCommentObserver::class);
        NajmBaharTransaction::observe(NajmBaharTransactionObserver::class);
        NajmBaharScheduledTransaction::observe(NajmBaharScheduledTransactionObserver::class);
        NajmBaharInvestment::observe(NajmBaharInvestmentObserver::class);
        NajmBaharAccount::observe(NajmBaharGenericModelObserver::class);
        NajmBaharSubAccount::observe(NajmBaharGenericModelObserver::class);
        NajmBaharLedgerEntry::observe(NajmBaharGenericModelObserver::class);
        NajmBaharFee::observe(NajmBaharGenericModelObserver::class);
        NajmBaharSalaryRule::observe(NajmBaharGenericModelObserver::class);
        NajmBaharSalaryRun::observe(NajmBaharGenericModelObserver::class);
        NajmBaharSalaryRunItem::observe(NajmBaharGenericModelObserver::class);
        NajmBaharProject::observe(NajmBaharGenericModelObserver::class);
        NajmBaharProjectReview::observe(NajmBaharGenericModelObserver::class);
        NajmBaharProjectCategory::observe(NajmBaharGenericModelObserver::class);
        Page::observe(ContentModelObserver::class);
        Blog::observe(ContentModelObserver::class);
        KbArticle::observe(ContentModelObserver::class);
        FaqQuestion::observe(ContentModelObserver::class);
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
