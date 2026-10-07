<?php

namespace Tests\Feature\Chronicle;

use App\Chronicle\ChronicleMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChronicleMilestoneManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_store_localized_date_as_canonical_milestone_date(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.chronicle.milestones.store'), [
            'occurred_on' => '۱۴۰۵/۰۷/۱۵',
            'title_fa' => 'نقطه عطف آزمایشی',
            'title_en' => 'Test milestone',
            'description_fa' => 'شرح آزمایشی',
            'sort_order' => 3,
            'is_published' => 1,
        ]);

        $response->assertRedirect(route('admin.chronicle.milestones.index'));

        $milestone = ChronicleMilestone::query()->sole();
        $this->assertSame('2026-10-07', $milestone->occurred_on->format('Y-m-d'));
        $this->assertSame('نقطه عطف آزمایشی', $milestone->title_translations['fa']);
        $this->assertTrue($milestone->is_published);
        $this->assertSame($admin->id, $milestone->created_by);
        $this->assertFalse(Schema::hasColumn('chronicle_milestones', 'earthcoop_year'));
    }

    public function test_public_chronicle_shows_only_published_milestones(): void
    {
        ChronicleMilestone::query()->create([
            'occurred_on' => '2026-10-07',
            'title_translations' => ['fa' => 'رویداد منتشرشده', 'en' => 'Published milestone'],
            'description_translations' => ['fa' => 'شرح عمومی'],
            'is_published' => true,
            'sort_order' => 1,
        ]);

        ChronicleMilestone::query()->create([
            'occurred_on' => '2026-10-06',
            'title_translations' => ['fa' => 'رویداد پیش‌نویس', 'en' => 'Draft milestone'],
            'description_translations' => ['fa' => 'نباید دیده شود'],
            'is_published' => false,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('chronicle.index'));

        $response->assertOk();
        $response->assertSee('رویداد منتشرشده');
        $response->assertDontSee('رویداد پیش‌نویس');
        $response->assertSee('سال 5 ارث‌کوپ');
    }

    public function test_chronicle_milestone_uses_active_locale_with_fallbacks(): void
    {
        ChronicleMilestone::query()->create([
            'occurred_on' => '2026-10-07',
            'title_translations' => [
                'fa' => 'عنوان فارسی',
                'en' => 'English milestone title',
                'ar' => 'عنوان عربي',
            ],
            'description_translations' => [
                'fa' => 'شرح فارسی',
                'en' => 'English milestone description',
            ],
            'is_published' => true,
        ]);

        app()->setLocale('en');

        $response = $this->get(route('chronicle.index'));

        $response->assertOk();
        $response->assertSee('English milestone title');
        $response->assertSee('English milestone description');
        $response->assertDontSee('عنوان فارسی');
    }

    public function test_non_admin_cannot_manage_chronicle_milestones(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.chronicle.milestones.index'));

        $response->assertRedirect('/home');
    }
}
