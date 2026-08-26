<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderMinistryAgencyService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderMinistryAgencyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('najm_hoda:founder_ops:delegation_grants');
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
    }

    public function test_delegated_safe_action_without_active_grant_is_not_executable_now(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('groups', []);

        $this->assertFalse(collect($agency['may_do_now'])->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'groups'
                && ($item['action'] ?? null) === 'summarize_activity'
        ));
        $this->assertTrue(collect($agency['may_prepare'])->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'groups'
                && ($item['action'] ?? null) === 'summarize_activity'
        ));
    }

    public function test_connected_delegated_safe_action_with_active_grant_is_executable_now(): void
    {
        $grant = app(\App\Services\NajmHoda\FounderOps\FounderDelegationGrantService::class)
            ->grant('groups', 'summarize_activity', 99, 2);

        $this->assertTrue($grant['success']);

        $agency = app(FounderMinistryAgencyService::class)->describe('groups', []);

        $this->assertTrue(collect($agency['may_do_now'])->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'groups'
                && ($item['action'] ?? null) === 'summarize_activity'
        ));
    }

    public function test_only_current_approval_items_are_reported_as_needing_founder_decision(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('communications', [[
            'kind' => 'approval',
            'domain' => 'email',
            'action' => 'send_email',
            'approval_request_id' => 'approval-1',
            'entity_type' => 'founder_email_draft',
            'entity_id' => 12,
            'title' => 'ایمیل منتظر تصمیم شماست',
        ]]);

        $this->assertCount(1, $agency['needs_founder_decision']);
        $this->assertSame('email', data_get($agency, 'needs_founder_decision.0.domain'));
        $this->assertSame('send_email', data_get($agency, 'needs_founder_decision.0.action'));
        $this->assertSame('approval-1', data_get($agency, 'needs_founder_decision.0.approval_request_id'));

        $this->assertFalse(collect($agency['needs_founder_decision'])->contains(
            fn (array $item): bool => ($item['action'] ?? null) === 'bulk_send'
        ));
    }

    public function test_blocked_secretariat_transport_is_never_reported_as_executable(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('secretariat', []);

        $blocked = collect($agency['blocked'])->first(
            fn (array $item): bool => ($item['domain'] ?? null) === 'secretariat'
                && ($item['action'] ?? null) === 'dispatch_formal_record'
        );

        $this->assertIsArray($blocked);
        $this->assertSame('blocked_dependency', $blocked['state']);
        $this->assertSame('real_transport_not_available', $blocked['reason_code']);
        $this->assertStringContainsString('ارسال واقعی', $blocked['reason']);
        $this->assertFalse(collect($agency['may_do_now'])->contains(
            fn (array $item): bool => ($item['action'] ?? null) === 'dispatch_formal_record'
        ));
    }

    public function test_forbidden_governance_actions_are_blocked_and_never_preparable_or_executable(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('governance', []);

        foreach (['alter_vote', 'alter_result'] as $action) {
            $this->assertTrue(collect($agency['blocked'])->contains(
                fn (array $item): bool => ($item['domain'] ?? null) === 'governance'
                    && ($item['action'] ?? null) === $action
                    && ($item['state'] ?? null) === 'protected'
            ));
            $this->assertFalse(collect($agency['may_prepare'])->contains(
                fn (array $item): bool => ($item['action'] ?? null) === $action
            ));
            $this->assertFalse(collect($agency['may_do_now'])->contains(
                fn (array $item): bool => ($item['action'] ?? null) === $action
            ));
        }
    }

    public function test_global_brief_scope_is_driven_by_current_report_domains_not_full_policy_dump(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('morning_brief', [[
            'kind' => 'attention',
            'domain' => 'stock',
            'priority' => 'P0',
            'title' => 'Settlement issue',
        ]]);

        $this->assertSame('global', $agency['scope']);
        $this->assertSame(['stock'], $agency['domain_keys']);
        $this->assertFalse(in_array('governance', $agency['domain_keys'], true));
        $this->assertFalse(in_array('email', $agency['domain_keys'], true));
    }

    public function test_agency_items_expose_human_presentation_without_losing_audit_codes(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('users_registration', []);

        $supportDraft = collect($agency['may_prepare'])->first(
            fn (array $item): bool => ($item['domain'] ?? null) === 'users'
                && ($item['action'] ?? null) === 'draft_support_response'
        );

        $this->assertIsArray($supportDraft);
        $this->assertSame('draft_support_response', $supportDraft['action']);
        $this->assertSame('پاسخ پشتیبانی را آماده کنم', $supportDraft['title']);
        $this->assertSame($supportDraft['title'], $supportDraft['display_title']);
        $this->assertStringContainsString('پاسخ مناسب', $supportDraft['display_explanation']);
    }

    public function test_blocked_items_explain_the_reason_in_manager_language(): void
    {
        $agency = app(FounderMinistryAgencyService::class)->describe('communications', []);

        $blocked = collect($agency['blocked'])->first(
            fn (array $item): bool => ($item['domain'] ?? null) === 'notifications'
                && ($item['action'] ?? null) === 'change_global_notification_defaults'
        );

        $this->assertIsArray($blocked);
        $this->assertSame('persisted_global_defaults_missing', $blocked['reason_code']);
        $this->assertSame('تنظیمات عمومی اعلان‌ها را تغییر دهم', $blocked['title']);
        $this->assertSame($blocked['title'], $blocked['display_title']);
        $this->assertStringContainsString('ذخیره پایدار', $blocked['reason']);
        $this->assertSame($blocked['reason'], $blocked['display_explanation']);
        $this->assertStringNotContainsString('persisted_global_defaults_missing', $blocked['reason']);
    }
}