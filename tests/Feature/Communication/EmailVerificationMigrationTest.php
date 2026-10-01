<?php

namespace Tests\Feature\Communication;

use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\Communication;
use App\Models\EmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class EmailVerificationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_code_is_queued_as_required_canonical_communication_without_direct_mail(): void
    {
        Mail::fake();
        Queue::fake();

        $email = 'verification-canonical@example.test';

        $this->post('/email/verify/send', ['email' => $email])
            ->assertRedirect(route('email.verify.form', ['email' => $email]));

        $verification = EmailVerification::query()->where('email', $email)->firstOrFail();
        $communication = Communication::query()
            ->where('source_type', 'email_verification')
            ->where('source_id', $email)
            ->firstOrFail();

        $this->assertSame('required', $communication->classification->value);
        $this->assertSame((string) $verification->code, (string) data_get($communication->context_snapshot, 'code'));
        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => null,
            'email' => $email,
            'status' => 'queued',
        ]);

        Mail::assertNothingSent();
        Queue::assertPushedOn('communications-critical', DeliverCommunicationRecipient::class);
    }

    public function test_repeated_send_replaces_code_but_does_not_reuse_stale_logical_communication(): void
    {
        Mail::fake();
        Queue::fake();

        $email = 'verification-repeat@example.test';

        $this->post('/email/verify/send', ['email' => $email]);
        $firstVerification = EmailVerification::query()->where('email', $email)->firstOrFail();
        $firstCode = (string) $firstVerification->code;

        $this->post('/email/verify/send', ['email' => $email]);
        $secondCode = (string) EmailVerification::query()->where('email', $email)->firstOrFail()->code;

        $this->assertSame(2, Communication::query()
            ->where('source_type', 'email_verification')
            ->where('source_id', $email)
            ->count());

        $contexts = Communication::query()
            ->where('source_type', 'email_verification')
            ->where('source_id', $email)
            ->orderBy('id')
            ->get()
            ->pluck('context_snapshot')
            ->map(fn (array $context): string => (string) ($context['code'] ?? ''));

        $this->assertSame($firstCode, $contexts->first());
        $this->assertSame($secondCode, $contexts->last());
    }
}
