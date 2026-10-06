<?php

namespace Tests\Feature\Communication;

use App\Models\EmailTemplate;
use App\Models\SystemEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyAdminSurfaceDeprecationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_email_management_pages_redirect_to_canonical_communication_center(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legacy = EmailTemplate::query()->create([
            'name' => 'Legacy',
            'subject' => 'Legacy subject',
            'body' => 'Legacy body',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/emails')
            ->assertRedirect(route('admin.communications.templates.index'));
        $this->actingAs($admin)->get('/admin/emails/create')
            ->assertRedirect(route('admin.communications.templates.index'));
        $this->actingAs($admin)->get('/admin/emails/'.$legacy->id.'/edit')
            ->assertRedirect(route('admin.communications.templates.index'));
        $this->actingAs($admin)->get('/admin/emails/send')
            ->assertRedirect(route('admin.communications.campaigns.create'));
    }

    public function test_legacy_email_template_mutations_are_blocked_and_do_not_change_source_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legacy = EmailTemplate::query()->create([
            'name' => 'Legacy',
            'subject' => 'Original subject',
            'body' => 'Original body',
            'is_active' => true,
        ]);

        $beforeCount = EmailTemplate::query()->count();

        $this->actingAs($admin)->post('/admin/emails', [
            'name' => 'Should not exist',
            'subject' => 'Blocked',
            'body' => 'Blocked',
            'is_active' => true,
        ])->assertRedirect(route('admin.communications.templates.index'));

        $this->assertSame($beforeCount, EmailTemplate::query()->count());

        $this->actingAs($admin)->put('/admin/emails/'.$legacy->id, [
            'name' => 'Mutated',
            'subject' => 'Mutated subject',
            'body' => 'Mutated body',
            'is_active' => true,
        ])->assertRedirect(route('admin.communications.templates.index'));

        $this->assertSame('Original subject', $legacy->fresh()->subject);

        $this->actingAs($admin)->delete('/admin/emails/'.$legacy->id)
            ->assertRedirect(route('admin.communications.templates.index'));

        $this->assertDatabaseHas('email_templates', ['id' => $legacy->id]);
    }

    public function test_legacy_system_email_management_is_read_only_deprecated_and_redirected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legacy = SystemEmail::query()->create([
            'name' => 'Legacy Support',
            'email' => 'legacy-support@example.test',
            'display_name' => 'Legacy Support',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->actingAs($admin)->get('/admin/system-emails')
            ->assertRedirect(route('admin.communications.senders.index'));
        $this->actingAs($admin)->get('/admin/system-emails/create')
            ->assertRedirect(route('admin.communications.senders.index'));
        $this->actingAs($admin)->get('/admin/system-emails/'.$legacy->id.'/edit')
            ->assertRedirect(route('admin.communications.senders.index'));

        $beforeCount = SystemEmail::query()->count();

        $this->actingAs($admin)->post('/admin/system-emails', [
            'name' => 'Blocked',
            'email' => 'blocked@example.test',
        ])->assertRedirect(route('admin.communications.senders.index'));
        $this->assertSame($beforeCount, SystemEmail::query()->count());

        $this->actingAs($admin)->put('/admin/system-emails/'.$legacy->id, [
            'name' => 'Mutated',
            'email' => 'mutated@example.test',
        ])->assertRedirect(route('admin.communications.senders.index'));
        $this->assertSame('legacy-support@example.test', $legacy->fresh()->email);

        $this->actingAs($admin)->delete('/admin/system-emails/'.$legacy->id)
            ->assertRedirect(route('admin.communications.senders.index'));
        $this->assertDatabaseHas('system_emails', ['id' => $legacy->id]);
    }

    public function test_admin_sidebar_exposes_only_canonical_communication_management_links(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertIsString($sidebar);
        $this->assertStringContainsString("route('admin.communications.index')", $sidebar);
        $this->assertStringContainsString("route('admin.communications.history')", $sidebar);
        $this->assertStringContainsString("route('admin.communications.failures')", $sidebar);
        $this->assertStringContainsString('تاریخچه ارسال‌ها', $sidebar);
        $this->assertStringContainsString('خطاها و تلاش مجدد', $sidebar);
        $this->assertStringContainsString("route('admin.communications.templates.index')", $sidebar);
        $this->assertStringContainsString("route('admin.communications.senders.index')", $sidebar);
        $this->assertStringContainsString("route('admin.communications.automations.index')", $sidebar);
        $this->assertStringContainsString("route('admin.communications.campaigns.index')", $sidebar);
        $this->assertStringNotContainsString("route('admin.emails.index')", $sidebar);
        $this->assertStringNotContainsString("route('admin.system-emails.index')", $sidebar);
        $this->assertStringNotContainsString("route('admin.emails.send')", $sidebar);
    }
}
