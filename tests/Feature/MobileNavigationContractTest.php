<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileNavigationContractTest extends TestCase
{
    public function test_mobile_drawer_keeps_core_navigation_grouped_and_collapsible(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        foreach ([
            "route('home')",
            "route('notifications.index')",
            "route('chat-requests.index')",
            "route('groups.index')",
            "route('history.index')",
            "route('history.election')",
            "route('history.poll')",
            "route('najm-bahar.dashboard')",
            "route('my-invation-code')",
            "route('secretariat.directory')",
            "route('support.kb.index')",
            "route('user.tickets.index')",
            "route('user.support-chat.index')",
        ] as $routeContract) {
            $this->assertStringContainsString($routeContract, $drawer);
        }

        foreach (['network', 'governance', 'economy', 'organization', 'support', 'explore'] as $section) {
            $this->assertStringContainsString("openSection === '{$section}'", $drawer);
        }

        $this->assertStringContainsString('x-data="{ openSection:', $drawer);
        $this->assertStringContainsString('@click="openSection = openSection ===', $drawer);
        $this->assertStringContainsString('navigation-section', $drawer);
    }

    public function test_mobile_drawer_and_registration_modal_use_localized_copy(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));
        $welcome = file_get_contents(resource_path('views/welcome.blade.php'));

        foreach ([
            'drawer_label', 'drawer_subtitle', 'drawer_close', 'drawer_auth_section',
            'drawer_about_section', 'request_invite', 'section_network', 'notifications',
            'private_conversations', 'invite_friends', 'section_governance',
            'my_location_governance', 'my_participations', 'my_earthcoop_story',
            'current_elections', 'my_election_history', 'current_polls', 'section_economy',
            'bahar_financial_account', 'value_share_ledger', 'value_share_auctions',
            'section_organization', 'my_secretariats', 'group_secretariat',
            'section_account_support', 'edit_account', 'knowledge_base', 'submit_ticket',
            'my_tickets', 'support_chat', 'section_explore_documents', 'documents',
            'documents_center', 'foundational_documents', 'fluid_election_guideline',
            'participation_credit_regulation', 'central_secretariat', 'install_app',
        ] as $key) {
            $this->assertStringContainsString("__('navigation.{$key}')", $drawer, "Missing drawer translation key: {$key}");
        }

        foreach ([
            'modal_welcome_title', 'modal_welcome_subtitle', 'modal_invite_only',
            'modal_invite_code', 'modal_invite_code_placeholder', 'modal_no_invite',
            'modal_request_invite', 'modal_terms_text', 'modal_terms_link',
            'modal_terms_agree', 'modal_terms_error', 'modal_already_member',
            'modal_login_link', 'btn_start_registration', 'btn_close',
        ] as $key) {
            $this->assertStringContainsString("__('langWelcome.{$key}')", $welcome, "Missing modal translation key: {$key}");
        }

        foreach (['fa', 'en', 'ar'] as $locale) {
            $translations = require resource_path("lang/{$locale}/navigation.php");
            foreach ([
                'drawer_label', 'drawer_subtitle', 'drawer_close', 'drawer_auth_section',
                'drawer_about_section', 'request_invite', 'section_network', 'notifications',
                'private_conversations', 'invite_friends', 'section_governance',
                'my_location_governance', 'my_participations', 'my_earthcoop_story',
                'current_elections', 'my_election_history', 'current_polls', 'section_economy',
                'bahar_financial_account', 'value_share_ledger', 'value_share_auctions',
                'section_organization', 'my_secretariats', 'group_secretariat',
                'section_account_support', 'edit_account', 'knowledge_base', 'submit_ticket',
                'my_tickets', 'support_chat', 'section_explore_documents', 'documents',
                'documents_center', 'foundational_documents', 'fluid_election_guideline',
                'participation_credit_regulation', 'central_secretariat', 'install_app',
                'install_app_short', 'open_menu', 'back',
            ] as $key) {
                $this->assertArrayHasKey($key, $translations, "Missing {$locale} navigation translation: {$key}");
            }
        }
    }

    public function test_public_sidebar_is_hidden_on_mobile_after_navigation_consolidation(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));

        $this->assertStringContainsString('.unified-public-sidebar { display: none !important; }', $header);
        $this->assertStringContainsString('@media (max-width: 1023px)', $header);
    }

    public function test_mobile_header_uses_the_navigation_drawer_and_account_components(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));

        $this->assertStringContainsString("@include('components.mobile-navigation-drawer'", $header);
        $this->assertStringContainsString("@include('components.mobile-account-menu')", $header);
        $this->assertStringContainsString('site-header-mobile-bar', $header);
        $this->assertStringContainsString('height: 60px !important', $header);
    }

    public function test_account_menu_is_viewport_anchored_on_mobile_and_compact_desktop(): void
    {
        $account = file_get_contents(resource_path('views/components/mobile-account-menu.blade.php'));
        $polish = file_get_contents(public_path('Css/header-mobile-polish.css'));

        $this->assertStringContainsString("route('profile.show')", $account);
        $this->assertStringContainsString("route('profile.edit')", $account);
        $this->assertStringContainsString("route('logout')", $account);
        $this->assertStringContainsString('mobile-account-dropdown', $account);
        $this->assertStringContainsString('@media (max-width: 1023px)', $polish);
        $this->assertStringContainsString('@media (min-width: 1024px) and (max-width: 1535px)', $polish);
        $this->assertStringContainsString('position: fixed !important;', $polish);
        $this->assertStringContainsString('z-index: 1400 !important;', $polish);
        $this->assertStringNotContainsString("route('auction.index')", $account);
        $this->assertStringNotContainsString("route('support.kb.index')", $account);
        $this->assertStringNotContainsString("route('secretariat.directory')", $account);
    }

    public function test_back_navigation_is_server_rendered_native_and_has_non_vite_runtime(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));
        $history = file_get_contents(resource_path('js/site-navigation-history.js'));
        $publicRuntime = file_get_contents(public_path('js/dark-mode.js'));
        $app = file_get_contents(resource_path('js/app.js'));
        $polish = file_get_contents(public_path('Css/header-mobile-polish.css'));

        $this->assertStringContainsString('data-earthcoop-history-back="true"', $header);
        $this->assertStringContainsString('site-header-mobile-back', $header);
        $this->assertStringContainsString("href=\"{{ route('home') }}\"", $header);
        $this->assertStringNotContainsString('url()->previous()', $header);

        $this->assertStringContainsString('window.history.back();', $history);
        $this->assertStringContainsString('document.referrer', $history);
        $this->assertStringNotContainsString('sessionStorage', $history);
        $this->assertStringContainsString('import "./site-navigation-history.js";', $app);

        $this->assertStringContainsString('window.earthcoopNavigateBack', $publicRuntime);
        $this->assertStringContainsString('window.history.back();', $publicRuntime);
        $this->assertStringContainsString('data-earthcoop-history-back', $publicRuntime);
        $this->assertStringContainsString('.site-header-mobile-back', $polish);
        $this->assertStringContainsString(':has(.site-header-mobile-back)', $polish);
    }

    public function test_guest_drawer_exposes_prominent_authentication_and_invite_code_actions(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        $this->assertStringContainsString("__('navigation.drawer_auth_section')", $drawer);
        $this->assertStringContainsString("route('login')", $drawer);
        $this->assertStringContainsString('openModal()', $drawer);
        $this->assertStringContainsString("route('invite')", $drawer);
        $this->assertStringContainsString("__('navigation.request_invite')", $drawer);
        $this->assertStringContainsString('guest-navigation-cta--login', $drawer);
        $this->assertStringContainsString('guest-navigation-cta--join', $drawer);
        $this->assertStringContainsString('guest-navigation-cta--invite', $drawer);
        $this->assertStringContainsString('background: #3b82f6 !important;', $drawer);
        $this->assertStringContainsString('background: #10b981 !important;', $drawer);
        $this->assertStringContainsString('background: #f59e0b !important;', $drawer);
    }

    public function test_header_polish_has_one_canonical_public_source_outside_vite(): void
    {
        $unified = file_get_contents(resource_path('views/layouts/unified.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("asset('Css/header-mobile-polish.css')", $unified);
        $this->assertFileExists(public_path('Css/header-mobile-polish.css'));
        $this->assertFileDoesNotExist(resource_path('css/header-mobile-polish.css'));
        $this->assertStringNotContainsString('header-mobile-polish.css', $app);
    }

    public function test_guest_header_is_balanced_cloaked_and_all_header_logos_animate(): void
    {
        $header = file_get_contents(resource_path('views/components/header-unified.blade.php'));
        $polish = file_get_contents(public_path('Css/header-mobile-polish.css'));

        $this->assertStringContainsString('[x-cloak]', $header);
        $this->assertStringContainsString('data-auth-state="{{ $isAuth ? \'authenticated\' : \'guest\' }}"', $header);
        $this->assertStringContainsString('[data-auth-state="guest"] .site-header-mobile-bar', $header);
        $this->assertStringContainsString('[data-auth-state="guest"] .site-header-mobile-account-slot', $header);
        $this->assertStringContainsString('animation: brand-logo-float 3s infinite ease-in-out', $header);
        $this->assertGreaterThanOrEqual(2, substr_count($header, 'brand-logo-animated'));
        $this->assertStringContainsString('[data-auth-state="guest"] .site-header-mobile-brand', $polish);
        $this->assertStringContainsString('[data-auth-state="guest"] .site-header-mobile-menu-slot', $polish);
    }

    public function test_navigation_drawer_stays_above_compact_account_trigger(): void
    {
        $polish = file_get_contents(public_path('Css/header-mobile-polish.css'));

        $this->assertStringContainsString('.mobile-account-root', $polish);
        $this->assertStringContainsString('z-index: 1050;', $polish);
        $this->assertStringContainsString('.mobile-navigation-drawer', $polish);
        $this->assertStringContainsString('z-index: 1500 !important;', $polish);
    }

    public function test_authenticated_mobile_navigation_exposes_location_governance_and_counts_direct_active_groups(): void
    {
        $drawer = file_get_contents(resource_path('views/components/mobile-navigation-drawer.blade.php'));

        $this->assertStringContainsString("route('location-governance.me')", $drawer);
        $this->assertStringContainsString("__('navigation.my_location_governance')", $drawer);
        $this->assertStringContainsString('CanonicalGroupMembershipReconciler', $drawer);
        $this->assertStringContainsString("whereIn('groups.id', \$mobileMaterializedGroupIds->all())", $drawer);
        $this->assertStringContainsString("wherePivot('status', 1)", $drawer);
    }

}
