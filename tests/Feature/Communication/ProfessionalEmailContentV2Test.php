<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationTemplate;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\Communication\CommunicationEmailPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProfessionalEmailContentV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_v2_publication_preserves_previous_variable_contracts(): void
    {
        $keys = [
            'onboarding.welcome',
            'reports.member.weekly',
            'reports.manager.weekly',
            'reports.inspector.weekly',
            'auth.email_verification',
            'auth.password_reset',
            'auth.invitation_issued',
            'membership.member_invitation',
            'auth.invitation_rejected',
            'faq.answer',
            'contact.reply',
            'najm_bahar.project_assigned',
        ];

        foreach ($keys as $key) {
            $template = CommunicationTemplate::query()->where('key', $key)->firstOrFail();
            $versions = DB::table('communication_template_versions')
                ->where('communication_template_id', $template->id)
                ->where('locale', 'fa')
                ->orderByDesc('version')
                ->limit(2)
                ->get();

            $this->assertCount(2, $versions, $key);
            $this->assertStringContainsString('earthcoop-email-content-v2', (string) $versions[0]->body, $key);
            $this->assertSame(
                json_decode((string) $versions[1]->variables_schema, true),
                json_decode((string) $versions[0]->variables_schema, true),
                $key,
            );
        }
    }

    public function test_shared_presenter_wraps_fragment_with_official_brand_and_logo(): void
    {
        $html = app(CommunicationEmailPresenter::class)
            ->present('پیام آزمایشی', '<p>متن پیام</p>');

        $this->assertStringContainsString('EarthCoop', $html);
        $this->assertStringContainsString('images/logo.png', $html);
        $this->assertStringContainsString('<p>متن پیام</p>', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
    }

    public function test_shared_presenter_does_not_double_wrap_complete_html_document(): void
    {
        $document = '<!DOCTYPE html><html><body><p>already complete</p></body></html>';

        $this->assertSame(
            $document,
            app(CommunicationEmailPresenter::class)->present('عنوان', $document),
        );
    }

    public function test_welcome_v2_is_real_onboarding_with_core_navigation_links(): void
    {
        $body = $this->latestBody('onboarding.welcome');

        $this->assertStringContainsString('سه قدم برای شروع', $body);
        $this->assertStringContainsString('/groups', $body);
        $this->assertStringContainsString('/history', $body);
        $this->assertStringContainsString('/location-governance/me', $body);
        $this->assertStringContainsString('{{profile_url}}', $body);
    }

    public function test_weekly_report_v2_is_action_oriented(): void
    {
        $body = $this->latestBody('reports.member.weekly');

        $this->assertStringContainsString('مشاهده مشارکت‌های من', $body);
        $this->assertStringContainsString('{{open_elections_count}}', $body);
        $this->assertStringContainsString('{{open_polls_count}}', $body);
        $this->assertStringContainsString('/profile/communication-preferences', $body);
    }

    public function test_ticket_reply_email_requires_reply_inside_earthcoop_and_keeps_temporal_component(): void
    {
        $ticket = Ticket::query()->create([
            'tracking_code' => 'TK-CONTENT01',
            'subject' => 'بررسی متن ایمیل',
            'message' => 'متن اولیه',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'کاربر آزمایشی',
            'email' => 'content@example.test',
        ]);

        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'message' => 'پاسخ پشتیبانی',
        ]);

        $html = view('emails.ticket-reply', [
            'ticket' => $ticket,
            'comment' => $comment,
            'user' => null,
            'commenter' => null,
        ])->render();

        $this->assertStringContainsString('EarthCoop', $html);
        $this->assertStringContainsString('images/logo.png', $html);
        $this->assertStringContainsString('برای پاسخ، تیکت را در EarthCoop باز کنید', $html);
        $this->assertStringNotContainsString('می‌توانید با پاسخ دادن به این ایمیل', $html);
    }

    private function latestBody(string $key): string
    {
        $template = CommunicationTemplate::query()->where('key', $key)->firstOrFail();

        return (string) DB::table('communication_template_versions')
            ->where('communication_template_id', $template->id)
            ->where('locale', 'fa')
            ->orderByDesc('version')
            ->value('body');
    }
}
