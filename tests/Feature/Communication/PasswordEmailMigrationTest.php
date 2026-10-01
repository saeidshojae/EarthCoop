<?php

namespace Tests\Feature\Communication;

use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\Communication;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PasswordEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_code_is_queued_as_required_canonical_communication_without_direct_mail(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create(['email' => 'password-canonical@example.test']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('password.reset.viewForm', ['email' => $user->email]));

        $verification = EmailVerification::query()->where('email', $user->email)->firstOrFail();
        $communication = Communication::query()
            ->where('source_type', 'password_reset')
            ->where('source_id', (string) $user->id)
            ->firstOrFail();

        $this->assertSame('required', $communication->classification->value);
        $this->assertSame((string) $verification->code, (string) data_get($communication->context_snapshot, 'code'));
        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'queued',
        ]);

        Mail::assertNothingSent();
        Queue::assertPushedOn('communications-critical', DeliverCommunicationRecipient::class);
    }

    public function test_each_password_reset_request_records_its_own_logical_communication(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create(['email' => 'password-repeat@example.test']);

        $this->post('/forgot-password', ['email' => $user->email]);
        $firstCode = (string) EmailVerification::query()->where('email', $user->email)->firstOrFail()->code;

        $this->post('/forgot-password', ['email' => $user->email]);
        $secondCode = (string) EmailVerification::query()->where('email', $user->email)->firstOrFail()->code;

        $communications = Communication::query()
            ->where('source_type', 'password_reset')
            ->where('source_id', (string) $user->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $communications);
        $this->assertSame($firstCode, (string) data_get($communications->first()?->context_snapshot, 'code'));
        $this->assertSame($secondCode, (string) data_get($communications->last()?->context_snapshot, 'code'));
    }
}
