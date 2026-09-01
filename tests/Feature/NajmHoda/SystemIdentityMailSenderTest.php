<?php

namespace Tests\Feature\NajmHoda;

use App\Services\SystemIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemIdentityMailSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_mail_sender_uses_support_team_identity(): void
    {
        $sender = app(SystemIdentityService::class)->mailSender('support');

        $this->assertSame('support@earthcoop.ir', $sender['address']);
        $this->assertSame('تیم پشتیبانی EarthCoop', $sender['name']);
        $this->assertSame('support@earthcoop.ir', $sender['reply_to']);
    }

    public function test_management_mail_sender_uses_management_team_identity(): void
    {
        $sender = app(SystemIdentityService::class)->mailSender('management');

        $this->assertSame('management@earthcoop.ir', $sender['address']);
        $this->assertSame('تیم مدیریت EarthCoop', $sender['name']);
        $this->assertSame('management@earthcoop.ir', $sender['reply_to']);
    }
}
