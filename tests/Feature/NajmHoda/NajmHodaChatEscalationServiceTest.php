<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\NajmHodaChatEscalationService;
use App\Services\NajmHodaIntegrationService;
use Mockery;
use Tests\TestCase;

class NajmHodaChatEscalationServiceTest extends TestCase
{
    public function test_failed_chat_requests_human_escalation_by_default(): void
    {
        $integration = Mockery::mock(NajmHodaIntegrationService::class);
        $service = new NajmHodaChatEscalationService($integration);

        $this->assertTrue($service->shouldEscalate('مشکل دارم', [
            'success' => false,
            'message' => 'متأسفانه مشکلی پیش آمد.',
        ]));
    }

    public function test_explicit_human_support_request_escalates_even_after_successful_answer(): void
    {
        $integration = Mockery::mock(NajmHodaIntegrationService::class);
        $service = new NajmHodaChatEscalationService($integration);

        $this->assertTrue($service->shouldEscalate('مشکلم حل نشد، پشتیبان انسانی می‌خواهم', [
            'success' => true,
            'message' => 'می‌توانم بیشتر راهنمایی کنم.',
        ]));
    }

    public function test_normal_successful_chat_does_not_escalate(): void
    {
        $integration = Mockery::mock(NajmHodaIntegrationService::class);
        $service = new NajmHodaChatEscalationService($integration);

        $this->assertFalse($service->shouldEscalate('چطور پروفایلم را کامل کنم؟', [
            'success' => true,
            'message' => 'از بخش پروفایل ادامه دهید.',
        ]));
    }
}
