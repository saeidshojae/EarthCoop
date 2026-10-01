<?php

namespace Tests\Feature\Communication;

use App\Models\EmailTemplate;
use App\Models\SystemEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyEmailAdminSurfaceDeprecationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_template_management_pages_redirect_to_canonical_templates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legacy = EmailTemplate::query()->create([
            'name' => 'Legacy',
            'subject' => 'Old subject',
            'body' => 'Old body',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/emails')
            ->assertRedirect(route('admin.communications.templates.index'));
        $this->actingAs($admin)->get('/admin/emails/create')
            ->assertRedirect(route('admin.communications.templates.index'));
        $this->actingAs($admin)->get('/admin/emails/'.$legacy->id.'/edit')
            ->assertRedirect(route('admin.communications.templates.show', 'legacy.email-template.'.$legacy->id));
    }

    public function test_legacy_template_mutation_endpoints_no_longer_mutate_legacy_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legacy = EmailTemplate::query()->create([
            'name' => 'Legacy',
            'subject' => 'Old subject',
            'body' => 'Old body',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post('/admin/emails', [
            'name' => 'Should not exist',
            'subject' => 'Blocked',
            'body' => 'Blocked',
        ])->assertRedirect(route('admin.communications.templates.index'));

        $this->actingAs($admin)->put('/admin/emails/'.$legacy->id, [
            'name' => 'Mutated',
            'subject' => 'Mutated',
            'body' => 'Mutated',
        ])->assertRedirect(route('admin.communications.templates.index'));

        $this->actingAs($admin)->delete('/admin/emails/'.$legacy->id)
            ->assertRedirect(route('admin.communications.templates.index'));

        $this->assertDatabaseMissing('email_templates', ['name' => 'Should not exist']);
        $this->assertDatabaseHas('email_templates', [
            'id' => $legacy->id,
            'name' => 'Legacy',
            'subject' => 'Old subject',
        ]);
    }

    public function test_legacy_system_email_management_is_redirect_only_and_non_mutating(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $legacy = SystemEmail::query()->create([
            'name' => 'Legacy Sender',
            'email' => 'legacy-sender@example.test',
            'display_name' => 'Legacy Sender',
            'is_active' => true,
            'is_default' => false,
        ]);

        $canonical = route('admin.communications.senders.index');
        $this->actingAs($admin)->get('/admin/system-emails')->assertRedirect($canonical);
        $this->actingAs($admin)->get('/admin/system-emails/create')->assertRedirect($canonical);
        $this->actingAs($admin)->get('/admin/system-emails/'.$legacy->id.'/edit')->assertRedirect($canonical);

        $this->actingAs($admin)->post('/admin/system-emails', [
            'name' => 'Should not exist',
            'email' => 'new@example.test',
        ])->assertRedirect($canonical);
        $this->actingAs($admin)->put('/admin/system-emails/'.$legacy->id, [
            'name' => 'Mutated',
            'email' => 'mutated@example.test',
        ])->assertRedirect($canonical);
        $this->actingAs($admin)->delete('/admin/system-emails/'.$legacy->id)
            ->assertRedirect($canonical);

        $this->assertDatabaseMissing('system_emails', ['name' => 'Should not exist']);
        $this->assertDatabaseHas('system_emails', [
            'id' => $legacy->id,
            'name' => 'Legacy Sender',
            'email' => 'legacy-sender@example.test',
        ]);
    }
}
