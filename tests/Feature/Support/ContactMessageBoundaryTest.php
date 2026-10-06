<?php

namespace Tests\Feature\Support;

use App\Models\ContactMessage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\ContactMessageConversionService;
use App\Services\Support\ContactMessageReplyService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContactMessageBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_creates_contact_message_instead_of_support_ticket(): void
    {
        $response = $this->post('/contact', [
            'name' => 'External Sender',
            'email' => 'external@example.test',
            'phone' => '+989120000000',
            'subject' => 'Partnership enquiry',
            'message' => 'I would like to discuss a possible collaboration with EarthCoop.',
            'company_website' => '',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'external@example.test',
            'subject' => 'Partnership enquiry',
            'status' => 'new',
        ]);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_contact_honeypot_submission_is_silently_discarded(): void
    {
        $response = $this->post('/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'subject' => 'SEO offer',
            'message' => 'Automated advertising message.',
            'company_website' => 'https://spam.example',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, ContactMessage::query()->count());
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_contact_route_has_public_rate_limit(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'contact.store');

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,10', $route->gatherMiddleware());
    }

    public function test_contact_message_cannot_be_converted_when_email_is_not_a_registered_user(): void
    {
        $message = ContactMessage::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.test',
            'subject' => 'Need help',
            'message' => 'I cannot access my account.',
            'status' => 'new',
        ]);

        $this->expectException(DomainException::class);

        app(ContactMessageConversionService::class)->convert($message);
    }

    public function test_authenticated_contact_message_can_be_converted_and_linked_to_member_ticket(): void
    {
        $user = User::factory()->create(['email' => 'member@example.test']);

        $message = ContactMessage::query()->create([
            'user_id' => $user->id,
            'name' => 'Member',
            'email' => 'member@example.test',
            'subject' => 'Account support',
            'message' => 'Please help me with my account.',
            'status' => 'reviewing',
        ]);

        $ticket = app(ContactMessageConversionService::class)->convert($message);

        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('Account support', $ticket->subject);
        $this->assertStringStartsWith('TK-', $ticket->tracking_code);

        $message->refresh();
        $this->assertSame('converted', $message->status);
        $this->assertSame($ticket->id, $message->converted_ticket_id);
    }
    public function test_guest_cannot_claim_member_email_to_gain_ticket_linkage(): void
    {
        User::factory()->create(['email' => 'member-claimed@example.test']);

        $message = ContactMessage::query()->create([
            'name' => 'Unverified Guest',
            'email' => 'member-claimed@example.test',
            'subject' => 'Claimed member message',
            'message' => 'This public message only claims the member email.',
            'status' => 'new',
        ]);

        $this->expectException(DomainException::class);

        app(ContactMessageConversionService::class)->convert($message);
    }

    public function test_external_contact_reply_uses_canonical_communication_center(): void
    {
        Queue::fake();

        $message = ContactMessage::query()->create([
            'name' => 'Guest',
            'email' => 'guest@example.test',
            'subject' => 'General enquiry',
            'message' => 'Could you send me more information?',
            'status' => 'reviewing',
        ]);

        $communication = app(ContactMessageReplyService::class)->reply(
            $message,
            'Thank you. We will send the requested information.',
            1,
        );

        $this->assertSame('contact_message', $communication->source_type);
        $this->assertSame((string) $message->id, $communication->source_id);
        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => null,
            'email' => 'guest@example.test',
        ]);

        $message->refresh();
        $this->assertSame('replied', $message->status);
        $this->assertNotNull($message->handled_at);
    }

}
