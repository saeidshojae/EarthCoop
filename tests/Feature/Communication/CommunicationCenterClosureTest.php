<?php

namespace Tests\Feature\Communication;

use App\Models\Communication;
use App\Models\ContactMessage;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Notifications\NajmBahar\ProjectAssigned;
use App\Services\EmailTicketIntegrationService;
use App\Services\Projects\ProjectAssignmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CommunicationCenterClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_ticket_creation_queues_canonical_ticket_created_email(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'first_name' => 'عضو',
            'last_name' => 'آزمایشی',
            'email' => 'member-ticket@example.test',
        ]);

        $this->actingAs($user)
            ->post(route('user.tickets.store'), [
                'subject' => 'مشکل آزمایشی',
                'message' => 'این پیام برای آزمون مسیر ایمیل canonical تیکت است.',
                'priority' => 'normal',
                'category' => 'technical',
            ])
            ->assertRedirect();

        $ticket = Ticket::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('communications', [
            'source_type' => 'support.ticket_created',
            'source_id' => (string) $ticket->id,
            'deduplication_key' => 'support.ticket_created:'.$ticket->id,
        ]);
        $this->assertDatabaseHas('communication_recipients', [
            'email' => $user->email,
            'user_id' => null,
        ]);
    }

    public function test_new_unthreaded_inbound_email_goes_to_contact_inbox_not_ticket(): void
    {
        $result = app(EmailTicketIntegrationService::class)->processIncomingEmail([
            'from' => ['email' => 'external@example.test', 'name' => 'External Sender'],
            'subject' => 'General partnership enquiry',
            'text_plain' => 'This is a new inbound email without a support ticket thread.',
            'message_id' => '<external-1@example.test>',
            'in_reply_to' => null,
            'references' => null,
        ]);

        $this->assertInstanceOf(ContactMessage::class, $result);
        $this->assertDatabaseHas('contact_messages', [
            'id' => $result->id,
            'email' => 'external@example.test',
            'source' => 'email_webhook',
            'status' => 'new',
        ]);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_matching_threaded_inbound_email_appends_to_existing_ticket(): void
    {
        $ticket = Ticket::query()->create([
            'tracking_code' => 'TK-THREAD01',
            'subject' => 'Existing support thread',
            'message' => 'Initial issue',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'Ticket Owner',
            'email' => 'owner@example.test',
        ]);

        TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'message' => 'Admin reply',
            'metadata' => ['message_id' => '<ticket-thread-1@earthcoop.test>'],
        ]);

        $result = app(EmailTicketIntegrationService::class)->processIncomingEmail([
            'from' => ['email' => 'owner@example.test', 'name' => 'Ticket Owner'],
            'subject' => 'Re: Existing support thread',
            'text_plain' => 'Reply from the same ticket mailbox.',
            'message_id' => '<reply-1@example.test>',
            'in_reply_to' => '<ticket-thread-1@earthcoop.test>',
            'references' => '<ticket-thread-1@earthcoop.test>',
        ]);

        $this->assertInstanceOf(Ticket::class, $result);
        $this->assertSame($ticket->id, $result->id);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => null,
        ]);
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_mismatched_sender_cannot_append_to_ticket_thread_and_is_routed_to_contact_inbox(): void
    {
        $ticket = Ticket::query()->create([
            'tracking_code' => 'TK-THREAD02',
            'subject' => 'Protected thread',
            'message' => 'Initial issue',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'Ticket Owner',
            'email' => 'owner2@example.test',
        ]);

        TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'message' => 'Admin reply',
            'metadata' => ['message_id' => '<ticket-thread-2@earthcoop.test>'],
        ]);

        $before = TicketComment::query()->where('ticket_id', $ticket->id)->count();

        $result = app(EmailTicketIntegrationService::class)->processIncomingEmail([
            'from' => ['email' => 'attacker@example.test', 'name' => 'Different Sender'],
            'subject' => 'Re: Protected thread',
            'text_plain' => 'This sender does not own the ticket mailbox.',
            'message_id' => '<reply-attacker@example.test>',
            'in_reply_to' => '<ticket-thread-2@earthcoop.test>',
            'references' => '<ticket-thread-2@earthcoop.test>',
        ]);

        $this->assertInstanceOf(ContactMessage::class, $result);
        $this->assertSame($before, TicketComment::query()->where('ticket_id', $ticket->id)->count());
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'attacker@example.test',
            'source' => 'email_webhook',
        ]);
    }

    public function test_project_assignment_keeps_in_app_notification_and_queues_canonical_email(): void
    {
        Queue::fake();
        Notification::fake();

        $recipient = User::factory()->create([
            'first_name' => 'بازبین',
            'last_name' => 'پروژه',
            'email' => 'reviewer@example.test',
        ]);

        $project = Project::factory()->pending()->create([
            'title' => 'پروژه ارجاع آزمایشی',
            'assigned_at' => now(),
            'required_capital' => 1000000,
        ]);

        app(ProjectAssignmentNotificationService::class)
            ->send($project, [$recipient], 'لطفاً بررسی شود');

        Notification::assertSentTo($recipient, ProjectAssigned::class);

        $communication = Communication::query()
            ->where('source_type', 'najm_bahar.project_assignment')
            ->where('source_id', (string) $project->id)
            ->firstOrFail();

        $this->assertStringStartsWith(
            'najm_bahar.project_assigned:'.$project->id.':'.$recipient->id.':',
            (string) $communication->deduplication_key,
        );

        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => $recipient->id,
            'email' => $recipient->email,
        ]);
    }

    public function test_email_webhook_route_is_registered_once(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->uri() === 'api/email/webhook')
            ->filter(fn ($route) => in_array('POST', $route->methods(), true));

        $this->assertCount(1, $routes);
    }
}
