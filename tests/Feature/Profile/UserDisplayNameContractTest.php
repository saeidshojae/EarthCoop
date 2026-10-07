<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Tests\TestCase;

class UserDisplayNameContractTest extends TestCase
{
    public function test_display_name_prefers_nickname_and_falls_back_to_legal_name(): void
    {
        $user = new User([
            'first_name' => 'سعید',
            'last_name' => 'شجاعی',
            'nickname' => 'سعیدِ ارث‌کوپ',
        ]);

        $this->assertSame('سعیدِ ارث‌کوپ', $user->displayName());

        $user->nickname = '   ';
        $this->assertSame('سعید شجاعی', $user->displayName());
        $this->assertSame('سعید شجاعی', trim($user->fullName()));
    }

    public function test_core_member_facing_surfaces_use_display_name_instead_of_legal_name(): void
    {
        $paths = [
            'resources/views/profile/profile-member-base.blade.php',
            'resources/views/profile/profile.blade.php',
            'resources/views/components/mobile-account-menu.blade.php',
            'resources/views/components/navbar.blade.php',
            'resources/views/components/user-dropdown-unified.blade.php',
            'resources/views/partials/nav-bar.blade.php',
            'resources/views/groups/show.blade.php',
            'resources/views/groups/partials/poll.blade.php',
            'resources/views/private-chats/index.blade.php',
            'resources/views/private-chats/show.blade.php',
            'resources/views/chat_request.blade.php',
            'resources/views/chat-requests/partials/body.blade.php',
            'resources/views/chat-requests/partials/profile-action.blade.php',
            'resources/views/partials/comments.blade.php',
            'resources/views/groups/comment.blade.php',
            'resources/views/user/tickets/show.blade.php',
            'resources/views/user/support-chat/index.blade.php',
            'resources/views/profile/member-invitations.blade.php',
            'resources/views/my-invation-code.blade.php',
            'resources/views/history/index-base.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));
            $this->assertStringNotContainsString('fullName()', $source, $path);
        }
    }

    public function test_member_facing_notifications_and_realtime_payloads_use_display_name(): void
    {
        $paths = [
            'app/Listeners/SendMentionNotification.php',
            'app/Listeners/SendGroupInvitationNotifications.php',
            'app/Listeners/SendCandidateAcceptedNotifications.php',
            'app/Listeners/SendMessageReportedNotifications.php',
            'app/Http/Controllers/MessageReactionController.php',
            'app/Http/Controllers/PrivateChatController.php',
            'app/Http/Controllers/ChatRequestController.php',
            'app/Http/Controllers/Group/GroupController.php',
            'app/Http/Controllers/User/SupportChatController.php',
            'app/Events/SupportChatMessageSent.php',
            'app/Services/NajmHoda/NajmHodaGroupAssistantService.php',
            'app/Services/Projects/ProjectAssignmentNotificationService.php',
            'app/Http/Controllers/Profile/CommunityStoryController.php',
            'app/Modules/Blog/Controllers/BlogController.php',
            'app/Notifications/AdminMessage.php',
            'app/Services/Actors/ActorDiscoveryService.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));
            $this->assertStringContainsString('displayName()', $source, $path);
        }
    }

    public function test_public_profile_does_not_repeat_nickname_as_secondary_label(): void
    {
        $memberProfile = file_get_contents(resource_path('views/profile/profile-member-base.blade.php'));
        $ownProfile = file_get_contents(resource_path('views/profile/profile.blade.php'));

        $this->assertStringContainsString('$user->displayName()', $memberProfile);
        $this->assertStringContainsString('Auth::user()->displayName()', $ownProfile);
        $this->assertStringNotContainsString('نام مستعار:', $memberProfile);
        $this->assertStringNotContainsString('نام مستعار:', $ownProfile);
    }

    public function test_admin_identity_surfaces_keep_legal_name_available(): void
    {
        $adminHeader = file_get_contents(resource_path('views/admin/partials/header.blade.php'));

        $this->assertStringContainsString('fullName()', $adminHeader);
    }

    public function test_group_chat_and_election_surfaces_do_not_rebuild_public_names_from_identity_fields(): void
    {
        $paths = [
            'resources/views/groups/partials/comment.blade.php',
            'resources/views/groups/partials/message.blade.php',
            'resources/views/groups/partials/post.blade.php',
            'resources/views/groups/partials/poll.blade.php',
            'resources/views/groups/partials/group_info_panel.blade.php',
            'resources/views/groups/modals/election_modal.blade.php',
            'resources/views/elections/portal.blade.php',
            'resources/views/history/election-history.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));
            $this->assertStringContainsString('displayName()', $source, $path);
        }

        $chatController = file_get_contents(app_path('Http/Controllers/Group/ChatController.php'));
        $systemicController = file_get_contents(app_path('Http/Controllers/Group/SystemicElectionChatController.php'));
        $electionPortal = file_get_contents(app_path('Http/Controllers/Elections/ElectionUserPortalController.php'));

        $this->assertStringContainsString('user:id,first_name,last_name,nickname,avatar', $chatController);
        $this->assertStringContainsString('user:id,first_name,last_name,nickname,avatar', $systemicController);
        $this->assertStringContainsString("'users.nickname'", $electionPortal);
    }

    public function test_public_financial_and_secretariat_surfaces_use_display_name_but_admin_keeps_legal_identity(): void
    {
        $publicPaths = [
            'resources/views/najm-bahar/reports/index.blade.php',
            'resources/views/najm-bahar/reports/pdf.blade.php',
            'resources/views/secretariat/show.blade.php',
            'resources/views/secretariat/correspondence/show.blade.php',
            'resources/views/secretariat/correspondence/create.blade.php',
            'app/Http/Controllers/NajmBaharReportController.php',
            'app/Http/Controllers/NajmBaharTransferController.php',
        ];

        foreach ($publicPaths as $path) {
            $source = file_get_contents(base_path($path));
            $this->assertStringContainsString('displayName()', $source, $path);
        }

        $adminHeader = file_get_contents(resource_path('views/admin/partials/header.blade.php'));
        $this->assertStringContainsString('fullName()', $adminHeader);
    }

}
