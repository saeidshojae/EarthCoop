<?php

namespace App\Services\NajmHoda\FounderOps;

use App\Models\Alley;
use App\Models\Announcement;
use App\Models\Election;
use App\Models\EmailTemplate;
use App\Models\ExperienceField;
use App\Models\FaqQuestion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\Invitation;
use App\Models\InvitationCode;
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
use App\Models\User;
use App\Modules\Blog\Models\Post as BlogPost;
use App\Modules\NajmBahar\Models\Project as NajmBaharProject;
use App\Modules\NajmBahar\Models\ProjectReview as NajmBaharProjectReview;
use App\Modules\NajmBahar\Models\ScheduledTransaction as NajmBaharScheduledTransaction;
use App\Modules\Secretariat\Models\SecretariatAttachmentScan;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Modules\Secretariat\Models\SecretariatDispatch;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Services\NajmHoda\Runtime\NajmHodaOpsHealthMonitor;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Carbon\CarbonImmutable;

class FounderOperationsSnapshotService
{
    public function __construct(
        protected RuntimeEventBus $events,
        protected NajmHodaOpsHealthMonitor $healthMonitor,
        protected FounderManagedDomainRegistry $domains
    ) {}

    public function snapshot(int $hours = 24): array
    {
        $hours = max(1, min($hours, 168));
        $now = CarbonImmutable::now();
        $since = $now->subHours($hours);
        $next24h = $now->addHours(24);

        $pendingLocations = [
            'alley' => Alley::query()->where('status', 0)->count(),
            'street' => Street::query()->where('status', 0)->count(),
            'neighborhood' => Neighborhood::query()->where('status', 0)->count(),
            'region' => Region::query()->where('status', 0)->count(),
            'rural' => Rural::query()->where('status', 0)->count(),
        ];
        $pendingReferences = [
            'experience' => ExperienceField::query()->where('status', 0)->count(),
            'occupational' => OccupationalField::query()->where('status', 0)->count(),
        ];

        $recentManagedEvents = collect($this->events->recent(null, 500))
            ->filter(function (array $event) use ($since): bool {
                $name = (string) ($event['event'] ?? '');
                if (! $this->isManagedEvent($name)) return false;
                $timestamp = $event['timestamp'] ?? null;
                if (! is_string($timestamp) || $timestamp === '') return true;
                try { return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($since); }
                catch (\Throwable) { return true; }
            })->values()->all();

        return [
            'window' => ['hours' => $hours, 'since' => $since->toIso8601String(), 'generated_at' => $now->toIso8601String()],
            'management_coverage' => $this->domains->coverage(),
            'users' => [
                'new_members' => User::query()->members()->where('created_at', '>=', $since)->count(),
                'new_verified_members' => User::query()->members()->where('created_at', '>=', $since)->whereNotNull('email_verified_at')->count(),
            ],
            'approvals' => [
                'references' => ['total' => array_sum($pendingReferences), 'by_type' => $pendingReferences],
                'locations' => ['total' => array_sum($pendingLocations), 'by_type' => $pendingLocations],
                'total' => array_sum($pendingReferences) + array_sum($pendingLocations),
            ],
            'support' => [
                'open' => Ticket::query()->where('status', 'open')->count(),
                'in_progress' => Ticket::query()->where('status', 'in-progress')->count(),
                'high_priority_active' => Ticket::query()->where('priority', 'high')->whereIn('status', ['open', 'in-progress'])->count(),
                'unassigned_active' => Ticket::query()->whereNull('assignee_id')->whereIn('status', ['open', 'in-progress'])->count(),
            ],
            'groups' => [
                'total' => Group::query()->count(), 'open' => Group::query()->where('is_open', 1)->count(),
                'active_in_window' => Group::query()->whereNotNull('last_activity_at')->where('last_activity_at', '>=', $since)->count(),
                'created_in_window' => Group::query()->where('created_at', '>=', $since)->count(),
            ],
            'governance' => [
                'active_elections' => Election::query()->where('is_closed', 0)->count(),
                'ending_within_24h' => Election::query()->where('is_closed', 0)->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $next24h])->count(),
                'overdue_open' => Election::query()->where('is_closed', 0)->whereNotNull('ends_at')->where('ends_at', '<', $now)->count(),
                'started_in_window' => Election::query()->where('starts_at', '>=', $since)->count(),
            ],
            'moderation' => [
                'pending_group_manager' => ReportedMessage::query()->where('status', 'pending_group_manager')->count(),
                'escalated_to_admin' => ReportedMessage::query()->where(fn ($q) => $q->where('escalated_to_admin', 1)->orWhere('status', 'escalated_to_admin'))->count(),
                'unresolved_total' => ReportedMessage::query()->whereNotIn('status', ['resolved_by_group_manager', 'resolved_by_admin'])->count(),
                'created_in_window' => ReportedMessage::query()->where('created_at', '>=', $since)->count(),
            ],
            'notifications' => [
                'announcements_in_window' => Announcement::query()->where('created_at', '>=', $since)->count(),
                'pinned_announcements_in_window' => Announcement::query()->where('created_at', '>=', $since)->where('should_pin', 1)->count(),
                'preference_records' => NotificationSetting::query()->count(),
            ],
            'growth' => [
                'pending_invitation_requests' => Invitation::query()->where('status', 0)->count(),
                'issued_requests_in_window' => Invitation::query()->where('status', 1)->where('updated_at', '>=', $since)->count(),
                'active_codes' => InvitationCode::query()->where('used', 0)->where(fn ($q) => $q->whereNull('expire_at')->orWhere('expire_at', '>', $now))->count(),
                'expired_unused_codes' => InvitationCode::query()->where('used', 0)->whereNotNull('expire_at')->where('expire_at', '<=', $now)->count(),
                'used_codes_in_window' => InvitationCode::query()->where('used', 1)->where(fn ($q) => $q->where('used_at', '>=', $since)->orWhere(fn ($qq) => $qq->whereNull('used_at')->where('updated_at', '>=', $since)))->count(),
            ],
            'email' => [
                'templates_total' => EmailTemplate::query()->count(),
                'active_templates' => EmailTemplate::query()->where('is_active', 1)->count(),
                'inactive_templates' => EmailTemplate::query()->where('is_active', 0)->count(),
                'changed_in_window' => EmailTemplate::query()->where('updated_at', '>=', $since)->count(),
            ],
            'blog' => [
                'posts_total' => BlogPost::query()->count(),
                'published_total' => BlogPost::query()->where('status', 'published')->count(),
                'published_in_window' => BlogPost::query()->where('status', 'published')->where('published_at', '>=', $since)->count(),
                'changed_in_window' => BlogPost::query()->where('updated_at', '>=', $since)->count(),
            ],
            'content' => [
                'pages_total' => Page::query()->count(),
                'published_pages' => Page::query()->where('is_published', 1)->count(),
                'kb_articles_total' => KbArticle::query()->count(),
                'kb_published' => KbArticle::query()->where('status', 'published')->count(),
                'faq_pending' => FaqQuestion::query()->whereNull('answer')->count(),
                'faq_unpublished_answered' => FaqQuestion::query()->whereNotNull('answer')->where('is_published', 0)->count(),
            ],
            'stock' => [
                'running_auctions' => Auction::query()->running()->count(),
                'scheduled_auctions' => Auction::query()->scheduled()->count(),
                'ending_within_24h' => Auction::query()->running()->whereNotNull('ends_at')->whereBetween('ends_at', [$now, $next24h])->count(),
                'expired_unsettled' => Auction::query()->whereNotNull('ends_at')->where('ends_at', '<', $now)->where('status', '!=', 'settled')->count(),
                'external_payment_intents' => [
                    'created' => ExternalPaymentIntent::query()->where('status', ExternalPaymentIntent::CREATED)->count(),
                    'pending' => ExternalPaymentIntent::query()->where('status', ExternalPaymentIntent::PENDING)->count(),
                    'confirmed' => ExternalPaymentIntent::query()->where('status', ExternalPaymentIntent::CONFIRMED)->count(),
                    'failed' => ExternalPaymentIntent::query()->where('status', ExternalPaymentIntent::FAILED)->count(),
                    'expired_non_terminal' => ExternalPaymentIntent::query()->whereIn('status', [ExternalPaymentIntent::CREATED, ExternalPaymentIntent::PENDING])->whereNotNull('expires_at')->where('expires_at', '<', $now)->count(),
                ],
                'settlement_allocations' => [
                    'prepared' => StockSettlementAllocation::query()->where('state', StockSettlementAllocation::PREPARED)->count(),
                    'settled' => StockSettlementAllocation::query()->where('state', StockSettlementAllocation::SETTLED)->count(),
                    'reconciliation_required' => StockSettlementAllocation::query()->where('state', StockSettlementAllocation::RECONCILIATION_REQUIRED)->count(),
                ],
            ],
            'secretariat' => [
                'records_total' => SecretariatRecord::query()->count(),
                'draft_records' => SecretariatRecord::query()->where('status', 'draft')->count(),
                'active_records' => SecretariatRecord::query()->where('status', 'active')->count(),
                'open_cases' => SecretariatCase::query()->whereNull('closed_at')->count(),
                'dispatches_due_within_24h' => SecretariatDispatch::query()->whereNull('completed_at')->whereNotNull('due_at')->whereBetween('due_at', [$now, $next24h])->count(),
                'overdue_dispatches' => SecretariatDispatch::query()->whereNull('completed_at')->whereNotNull('due_at')->where('due_at', '<', $now)->count(),
                'responses_due' => SecretariatDispatch::query()->where('expects_response', 1)->whereNull('completed_at')->count(),
                'attachment_scans_in_window' => SecretariatAttachmentScan::query()->where('created_at', '>=', $since)->count(),
            ],
            'najm_bahar' => [
                'projects_total' => NajmBaharProject::query()->count(),
                'projects_submitted' => NajmBaharProject::query()->where('status', 'submitted')->count(),
                'projects_under_review' => NajmBaharProject::query()->where('status', 'under_review')->count(),
                'revision_requested' => NajmBaharProject::query()->where('status', 'revision_requested')->count(),
                'review_events_in_window' => NajmBaharProjectReview::query()->where('created_at', '>=', $since)->count(),
                'scheduled_due_within_24h' => NajmBaharScheduledTransaction::query()->whereNotIn('status', ['completed', 'executed', 'cancelled'])->whereBetween('execute_at', [$now, $next24h])->count(),
                'scheduled_overdue' => NajmBaharScheduledTransaction::query()->whereNotIn('status', ['completed', 'executed', 'cancelled'])->where('execute_at', '<', $now)->count(),
            ],
            'admin_configuration' => [
                'group_setting_records' => GroupSetting::query()->count(),
                'system_setting_records' => Setting::query()->count(),
            ],
            'runtime_health' => $this->healthMonitor->snapshot(),
            'recent_managed_events' => $recentManagedEvents,
        ];
    }

    protected function isManagedEvent(string $name): bool
    {
        if ($name === '') return false;
        foreach ($this->domains->all() as $domain) {
            foreach ((array) ($domain['event_prefixes'] ?? []) as $prefix) {
                if (($prefix = (string) $prefix) !== '' && str_starts_with($name, $prefix)) return true;
            }
        }
        return false;
    }
}
