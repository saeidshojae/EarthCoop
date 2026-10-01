<?php

namespace Tests\Unit\Temporal;

use App\Models\User;
use App\Temporal\Context\TemporalContextResolver;
use PHPUnit\Framework\TestCase;

class TemporalContextResolverTest extends TestCase
{
    private function resolver(?callable $activeLocaleResolver = null, ?callable $activeUserResolver = null): TemporalContextResolver
    {
        return new TemporalContextResolver(
            ['fa' => 'jalali', 'en' => 'gregorian', 'ar' => 'gregorian'],
            'gregorian',
            'UTC',
            'fa',
            $activeLocaleResolver ? \Closure::fromCallable($activeLocaleResolver) : null,
            $activeUserResolver ? \Closure::fromCallable($activeUserResolver) : null,
        );
    }

    public function test_persian_locale_resolves_jalali_and_persian_digits(): void
    {
        $context = $this->resolver()->forLocale('fa', 'Asia/Tehran');

        $this->assertSame('jalali', $context->calendar());
        $this->assertSame('persian', $context->numberingSystem());
        $this->assertSame('Asia/Tehran', $context->timezone());
    }

    public function test_locale_variants_use_base_locale_calendar_policy(): void
    {
        $this->assertSame('jalali', $this->resolver()->forLocale('fa-IR', 'Asia/Tehran')->calendar());
        $this->assertSame('gregorian', $this->resolver()->forLocale('en-GB', 'Europe/London')->calendar());
    }

    public function test_arabic_defaults_to_gregorian(): void
    {
        $context = $this->resolver()->forLocale('ar', 'Asia/Riyadh');

        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('latin', $context->numberingSystem());
    }

    public function test_invalid_timezone_falls_back_to_configured_default(): void
    {
        $this->assertSame('UTC', $this->resolver()->forLocale('en', 'Not/A_Timezone')->timezone());
    }

    public function test_user_persisted_locale_and_timezone_override_runtime_context(): void
    {
        $user = new User();
        $user->locale = 'en';
        $user->timezone = 'Europe/London';

        $context = $this->resolver(static fn (): string => 'fa')->forUser($user);

        $this->assertSame('en', $context->locale());
        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('Europe/London', $context->timezone());
    }

    public function test_recipient_with_preferences_is_independent_of_worker_locale(): void
    {
        $recipient = new User();
        $recipient->locale = 'ar';
        $recipient->timezone = 'Asia/Riyadh';

        $context = $this->resolver(static fn (): string => 'en')->forRecipient($recipient);

        $this->assertSame('ar', $context->locale());
        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('Asia/Riyadh', $context->timezone());
    }

    public function test_recipient_without_preferences_uses_system_fallback_not_worker_locale(): void
    {
        $context = $this->resolver(static fn (): string => 'en')->forRecipient(new User());

        $this->assertSame('fa', $context->locale());
        $this->assertSame('jalali', $context->calendar());
        $this->assertSame('UTC', $context->timezone());
    }

    public function test_explicit_recipient_override_wins_over_persisted_preferences(): void
    {
        $recipient = new User();
        $recipient->locale = 'fa';
        $recipient->timezone = 'Asia/Tehran';

        $context = $this->resolver()->forRecipient($recipient, 'en', 'Europe/London');

        $this->assertSame('en', $context->locale());
        $this->assertSame('Europe/London', $context->timezone());
    }

    public function test_default_context_tracks_active_runtime_locale(): void
    {
        $context = $this->resolver(static fn (): string => 'en')->defaultContext();

        $this->assertSame('en', $context->locale());
        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('UTC', $context->timezone());
    }

    public function test_default_context_uses_active_user_timezone_without_overriding_runtime_locale(): void
    {
        $user = new User();
        $user->locale = 'fa';
        $user->timezone = 'Pacific/Honolulu';

        $context = $this->resolver(
            static fn (): string => 'en',
            static fn (): User => $user,
        )->defaultContext();

        $this->assertSame('en', $context->locale());
        $this->assertSame('gregorian', $context->calendar());
        $this->assertSame('Pacific/Honolulu', $context->timezone());
    }
}
