<?php

namespace Tests\Feature\Temporal;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class TemporalLocalePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_locale_is_used_when_session_has_no_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user);
        Session::forget('locale');

        app(SetLocale::class)->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_explicit_session_locale_is_persisted_for_authenticated_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->actingAs($user);
        Session::put('locale', 'ar');

        app(SetLocale::class)->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame('ar', app()->getLocale());
        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_invalid_stored_locale_falls_back_to_application_default(): void
    {
        $user = User::factory()->create(['locale' => 'xx']);
        $this->actingAs($user);
        Session::forget('locale');

        app(SetLocale::class)->handle(Request::create('/'), fn () => response('ok'));

        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_resolved_locale_is_exposed_in_non_http_only_cookie_for_frontend_temporal_runtime(): void
    {
        Session::put('locale', 'en');
        $request = Request::create('/');

        $response = app(SetLocale::class)->handle($request, fn () => response('ok'));
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'earthcoop_locale');

        $this->assertNotNull($cookie);
        $this->assertSame('en', $cookie->getValue());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
    }
}
