<?php

namespace App\Providers;

use App\Models\Alley;
use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Election;
use App\Models\EmailTemplate;
use App\Models\ExperienceField;
use App\Models\FaqQuestion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\KbArticle;
use App\Models\Neighborhood;
use App\Models\NotificationSetting;
use App\Models\OccupationalField;
use App\Models\Page;
use App\Models\Region;
use App\Models\ReportedMessage;
use App\Models\Rural;
use App\Models\Setting;
use App\Models\Street;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Modules\Blog\Models\Post as BlogPost;
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
use App\Observers\NajmHoda\FounderManagedContentObserver;
use App\Observers\NajmHoda\FounderOperationalDomainObserver;
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

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            \App\Listeners\CaptureNajmHodaAuthLifecycle::class,
        ],
        AuthLogin::class => [\App\Listeners\CaptureNajmHodaAuthLifecycle::class],
        AuthFailed::class => [\App\Listeners\CaptureNajmHodaAuthLifecycle::class],
        AuthLogout::class => [\App\Listeners\CaptureNajmHodaAuthLifecycle::class],
        AuthPasswordReset::class => [\App\Listeners\CaptureNajmHodaAuthLifecycle::class],
        \App\Events\MessageCreated::class => [
            \App\Listeners\SendGroupMessageNotifications::class,
            \App\Listeners\HandleNajmHodaGroupMessage::class,
            \App\Listeners\CaptureNajmHodaRuntimeInput::class,
        ],
        \App\Events\GroupPollUpdated::class => [\App\Listeners\CaptureNajmHodaRuntimeInput::class],
        \App\Events\GroupFeedUpdated::class => [\App\Listeners\CaptureNajmHodaRuntimeInput::class],
        \App\Events\UserMentioned::class => [\App\Listeners\SendMentionNotification::class],
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
        \App\Events\Elections\ElectionAppointmentApplied::class => [
            \App\Listeners\AwardElectionAppointmentParticipation::class,
        ],
        \App\Events\CandidateAccepted::class => [\App\Listeners\SendCandidateAcceptedNotifications::class],
        \App\Events\CommentCreated::class => [
            \App\Listeners\SendCommentCreatedNotifications::class,
            \App\Listeners\BridgeSystemGroupArtifactToRealtimeFeed::class,
        ],
        \App\Events\GroupInvitation::class => [\App\Listeners\SendGroupInvitationNotifications::class],
        \App\Events\MessageReported::class => [\App\Listeners\SendMessageReportedNotifications::class],
        \App\Events\BidLost::class => [
            \App\Listeners\SendBidLostNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\BidCancelled::class => [
            \App\Listeners\SendBidCancelledNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
            \App\Listeners\AwardBidCancellationReputation::class,
        ],
        \App\Events\WalletSettled::class => [
            \App\Listeners\SendWalletSettledNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\WalletReleased::class => [
            \App\Listeners\SendWalletReleasedNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\WalletHeld::class => [
            \App\Listeners\SendWalletHeldNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\SharesReceived::class => [
            \App\Listeners\SendSharesReceivedNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\SharesGifted::class => [
            \App\Listeners\SendSharesGiftedNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\StockPriceChanged::class => [
            \App\Listeners\SendStockPriceChangedNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
        \App\Events\AuctionReminder::class => [
            \App\Listeners\SendAuctionReminderNotifications::class,
            \App\Listeners\CaptureNajmHodaStockRuntimeInput::class,
        ],
    ];

    public function boot()
    {
        User::observe(FounderUserObserver::class);
        User::observe(\App\Observers\ProfileMilestoneReputationObserver::class);
        \App\Models\Report::observe(\App\Observers\ConfirmedReportReputationObserver::class);
        ReportedMessage::observe(\App\Observers\ConfirmedReportReputationObserver::class);
        \App\Models\PrivateChatReport::observe(\App\Observers\ConfirmedReportReputationObserver::class);

        ExperienceField::observe(FounderReferenceDataObserver::class);
        OccupationalField::observe(FounderReferenceDataObserver::class);
        Alley::observe(FounderReferenceDataObserver::class);
        Street::observe(FounderReferenceDataObserver::class);
        Neighborhood::observe(FounderReferenceDataObserver::class);
        Region::observe(FounderReferenceDataObserver::class);
        Rural::observe(FounderReferenceDataObserver::class);
        EmailTemplate::observe(FounderManagedContentObserver::class);
        BlogPost::observe(FounderManagedContentObserver::class);

        Group::observe(FounderOperationalDomainObserver::class);
        Election::observe(FounderOperationalDomainObserver::class);
        ReportedMessage::observe(FounderOperationalDomainObserver::class);
        Announcement::observe(FounderOperationalDomainObserver::class);
        NotificationSetting::observe(FounderOperationalDomainObserver::class);
        GroupSetting::observe(FounderOperationalDomainObserver::class);
        Setting::observe(FounderOperationalDomainObserver::class);

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

    public function shouldDiscoverEvents()
    {
        return false;
    }
}
