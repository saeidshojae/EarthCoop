<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Context\NajmHodaGroundedPageResponder;
use Tests\TestCase;

class GroundedPageResponderTest extends TestCase
{
    public function test_page_identity_and_capabilities_are_answered_from_validated_context_without_llm(): void
    {
        $response = (new NajmHodaGroundedPageResponder())->respond(
            'من الان در چه صفحه‌ای هستم؟ چه کارهایی را خودم می‌توانم انجام بدهم و چه کارهایی را می‌توانم به تو بسپارم؟',
            $this->groupContext()
        );

        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['grounded_page_response']);
        $this->assertStringContainsString('گفتگوی گروه', $response['message']);
        $this->assertStringContainsString('ارسال پیام متنی', $response['message']);
        $this->assertStringContainsString('ایجاد نظرسنجی', $response['message']);
        $this->assertStringContainsString('تفویض ایجاد نظرسنجی به نجم هدا', $response['message']);
        $this->assertStringNotContainsString('کیف‌پول خارجی', $response['message']);
        $this->assertStringNotContainsString('example.com', $response['message']);
    }

    public function test_specific_how_to_uses_only_registered_contract_steps(): void
    {
        $response = (new NajmHodaGroundedPageResponder())->respond(
            'دقیقاً چطور یک نظرسنجی ایجاد کنم؟',
            $this->groupContext()
        );

        $this->assertIsArray($response);
        $this->assertStringContainsString('ایجاد نظرسنجی', $response['message']);
        $this->assertStringContainsString('حداقل دو گزینه', $response['message']);
        $this->assertStringContainsString('انتشار نظرسنجی', $response['message']);
        $this->assertStringNotContainsString('مقاله', $response['message']);
        $this->assertStringNotContainsString('knowledge-base', $response['message']);
    }

    public function test_open_ended_advice_is_left_for_the_cognitive_model(): void
    {
        $response = (new NajmHodaGroundedPageResponder())->respond(
            'برای بهتر مطرح کردن ایده آب در این گروه چه پیشنهادی داری؟',
            $this->groupContext()
        );

        $this->assertNull($response);
    }

    /** @return array<string,mixed> */
    private function groupContext(): array
    {
        return [
            'page_kind' => 'group_chat',
            'page_label' => 'گفتگوی گروه',
            'capability_contracts' => [
                [
                    'id' => 'send_message',
                    'label' => 'ارسال پیام متنی',
                    'summary' => 'پیام متنی را از composer پایین صفحه ارسال کنید.',
                    'ui' => [
                        'steps' => [
                            'متن را در کادر پیام وارد کنید.',
                            'دکمه ارسال را بزنید.',
                        ],
                    ],
                ],
                [
                    'id' => 'create_poll',
                    'label' => 'ایجاد نظرسنجی',
                    'summary' => 'سؤال، مدت و حداقل دو گزینه را تعریف کنید.',
                    'ui' => [
                        'steps' => [
                            'منوی پیوست را باز کنید.',
                            '«ایجاد نظرسنجی» را انتخاب کنید.',
                            'سؤال و مدت فعال بودن را وارد کنید و حداقل دو گزینه بسازید.',
                            'دکمه «انتشار نظرسنجی» را بزنید.',
                        ],
                    ],
                ],
            ],
            'delegated_actions' => [
                [
                    'id' => 'najm_hoda_create_poll',
                    'label' => 'تفویض ایجاد نظرسنجی به نجم هدا',
                    'requires_confirmation' => true,
                    'conversation_visibility' => 'private_widget',
                    'result_visibility' => 'group_feed',
                ],
            ],
        ];
    }
}
