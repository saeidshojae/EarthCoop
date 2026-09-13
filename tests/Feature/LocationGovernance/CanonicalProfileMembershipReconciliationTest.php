<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\AgeGroup;
use App\Models\ExperienceField;
use App\Models\Group;
use App\Models\OccupationalField;
use App\Models\User;
use App\Services\Groups\CanonicalGroupMembershipReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Morilog\Jalali\Jalalian;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class CanonicalProfileMembershipReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_taxonomy_profile_update_reconciles_canonical_ancestry_without_creating_legacy_groups(): void
    {
        $this->enableStageC();
        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();
        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $oldProfessionValue = 'occupational_field:'.(int) $user->occupationalFields()->firstOrFail()->id;
        $oldSpecialtyValue = 'experience_field:'.(int) $user->experienceFields()->firstOrFail()->id;

        $professionRoot = OccupationalField::create(['name' => 'فرهنگیان', 'status' => 1]);
        $professionParent = OccupationalField::create(['name' => 'معلمان ابتدایی', 'parent_id' => $professionRoot->id, 'status' => 1]);
        $professionLeaf = OccupationalField::create(['name' => 'معلمان پایه اول', 'parent_id' => $professionParent->id, 'status' => 1]);

        $specialtyRoot = ExperienceField::create(['name' => 'آموزش', 'status' => 1]);
        $specialtyParent = ExperienceField::create(['name' => 'آموزش ابتدایی', 'parent_id' => $specialtyRoot->id, 'status' => 1]);
        $specialtyLeaf = ExperienceField::create(['name' => 'تدریس پایه اول', 'parent_id' => $specialtyParent->id, 'status' => 1]);

        $this->actingAs($user)->put(route('profile.update.experience'), [
            'occupational_fields' => [$professionLeaf->id],
            'experience_fields' => [$specialtyLeaf->id],
        ])->assertRedirect(route('profile.edit'));

        foreach ([$professionRoot, $professionParent, $professionLeaf] as $field) {
            $group = Group::query()
                ->where('governance_area_id', $area->id)
                ->where('dimension_key', 'profession')
                ->where('dimension_value_key', 'occupational_field:'.$field->id)
                ->firstOrFail();
            $this->assertSame(1, (int) $user->groups()->whereKey($group->id)->firstOrFail()->pivot->status);
        }

        foreach ([$specialtyRoot, $specialtyParent, $specialtyLeaf] as $field) {
            $group = Group::query()
                ->where('governance_area_id', $area->id)
                ->where('dimension_key', 'specialty')
                ->where('dimension_value_key', 'experience_field:'.$field->id)
                ->firstOrFail();
            $this->assertSame(1, (int) $user->groups()->whereKey($group->id)->firstOrFail()->pivot->status);
        }

        foreach ([['profession', $oldProfessionValue], ['specialty', $oldSpecialtyValue]] as [$dimension, $value]) {
            $oldGroup = Group::query()
                ->where('governance_area_id', $area->id)
                ->where('dimension_key', $dimension)
                ->where('dimension_value_key', $value)
                ->firstOrFail();
            $this->assertSame(0, (int) $user->groups()->whereKey($oldGroup->id)->firstOrFail()->pivot->status);
        }

        $this->assertFalse(Group::query()
            ->whereNull('governance_area_id')
            ->where(function ($query) use ($professionLeaf, $specialtyLeaf): void {
                $query->where('specialty_id', $professionLeaf->id)
                    ->orWhere('experience_id', $specialtyLeaf->id);
            })
            ->exists(), 'Canonical profile updates must not create legacy taxonomy groups.');
    }

    public function test_general_profile_update_reconciles_gender_and_age_memberships_immediately(): void
    {
        $this->enableStageC();
        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();

        AgeGroup::create(['title' => '35-44', 'min_age' => 35, 'max_age' => 44]);
        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $oldGender = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'gender')
            ->where('dimension_value_key', 'gender:male')
            ->firstOrFail();
        $oldAge = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'age')
            ->firstOrFail();

        $targetBirthDate = now()->subYears(40)->startOfDay();
        $jalali = Jalalian::fromCarbon($targetBirthDate);

        $this->actingAs($user)->put(route('profile.update.general'), [
            'gender' => 'female',
            'birth_date' => [$jalali->getDay(), $jalali->getMonth(), $jalali->getYear()],
        ])->assertRedirect();

        $femaleGroup = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'gender')
            ->where('dimension_value_key', 'gender:female')
            ->firstOrFail();
        $newAge = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'age')
            ->where('id', '!=', $oldAge->id)
            ->firstOrFail();

        $this->assertSame(1, (int) $user->groups()->whereKey($femaleGroup->id)->firstOrFail()->pivot->status);
        $this->assertSame(1, (int) $user->groups()->whereKey($newAge->id)->firstOrFail()->pivot->status);
        $this->assertSame(0, (int) $user->groups()->whereKey($oldGender->id)->firstOrFail()->pivot->status);
        $this->assertSame(0, (int) $user->groups()->whereKey($oldAge->id)->firstOrFail()->pivot->status);
    }

    public function test_canonical_profile_taxonomy_write_stays_group_dark_while_stage_c_groups_are_disabled(): void
    {
        $this->enableRegistrationOnly();
        ['user' => $user] = MembershipFixture::canonicalUser();

        $profession = OccupationalField::create(['name' => 'فرهنگیان', 'status' => 1]);
        $specialty = ExperienceField::create(['name' => 'آموزش', 'status' => 1]);
        $groupCountBefore = Group::query()->count();
        $legacyGroupCountBefore = Group::query()->whereNull('governance_area_id')->count();

        $this->actingAs($user)->put(route('profile.update.experience'), [
            'occupational_fields' => [$profession->id],
            'experience_fields' => [$specialty->id],
        ])->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->occupationalFields()->whereKey($profession->id)->exists());
        $this->assertTrue($user->fresh()->experienceFields()->whereKey($specialty->id)->exists());
        $this->assertSame($groupCountBefore, Group::query()->count(), 'Registration cutover must not create any groups while Stage C groups are dark.');
        $this->assertSame($legacyGroupCountBefore, Group::query()->whereNull('governance_area_id')->count(), 'Registration cutover must not fall back to legacy group creation while Stage C groups are dark.');
    }

    public function test_canonical_general_profile_write_stays_group_dark_while_stage_c_groups_are_disabled(): void
    {
        $this->enableRegistrationOnly();
        ['user' => $user] = MembershipFixture::canonicalUser();

        AgeGroup::create(['title' => '35-44', 'min_age' => 35, 'max_age' => 44]);
        $targetBirthDate = now()->subYears(40)->startOfDay();
        $jalali = Jalalian::fromCarbon($targetBirthDate);
        $groupCountBefore = Group::query()->count();
        $legacyGroupCountBefore = Group::query()->whereNull('governance_area_id')->count();

        $this->actingAs($user)->put(route('profile.update.general'), [
            'gender' => 'female',
            'birth_date' => [$jalali->getDay(), $jalali->getMonth(), $jalali->getYear()],
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('female', $fresh->gender);
        $this->assertSame(40, $fresh->birth_date->age);
        $this->assertSame($groupCountBefore, Group::query()->count(), 'Canonical general profile writes must not create any groups while Stage C groups are dark.');
        $this->assertSame($legacyGroupCountBefore, Group::query()->whereNull('governance_area_id')->count(), 'Canonical general profile writes must not create legacy age/gender groups while Stage C groups are dark.');
    }

    public function test_admin_user_update_reconciles_canonical_age_and_gender_memberships(): void
    {
        $this->enableStageC();
        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();
        AgeGroup::create(['title' => '35-44', 'min_age' => 35, 'max_age' => 44]);
        app(CanonicalGroupMembershipReconciler::class)->reconcile($user);

        $user->forceFill([
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'national_id' => '1234567891',
            'phone' => '09123456789',
        ])->save();

        $oldGender = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'gender')
            ->where('dimension_value_key', 'gender:male')
            ->firstOrFail();
        $oldAge = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'age')
            ->firstOrFail();

        $targetBirthDate = now()->subYears(40)->startOfDay();
        $jalali = Jalalian::fromCarbon($targetBirthDate);
        $admin = User::factory()->create();

        $this->withoutMiddleware([
            AdminMiddleware::class,
            PermissionMiddleware::class,
        ]);
        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'email' => 'canonical-admin-update-'.uniqid().'@example.test',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'birth_date' => [$jalali->getDay(), $jalali->getMonth(), $jalali->getYear()],
            'gender' => 'female',
            'national_id' => '1234567806',
            'phone' => '09123456780',
            'password' => null,
        ])->assertRedirect(route('admin.users.index'));

        $fresh = $user->fresh();
        $this->assertSame('female', $fresh->gender);
        $this->assertSame(40, $fresh->birth_date->age);

        $femaleGroup = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'gender')
            ->where('dimension_value_key', 'gender:female')
            ->firstOrFail();
        $newAge = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'age')
            ->where('id', '!=', $oldAge->id)
            ->firstOrFail();

        $this->assertSame(1, (int) $user->groups()->whereKey($femaleGroup->id)->firstOrFail()->pivot->status);
        $this->assertSame(1, (int) $user->groups()->whereKey($newAge->id)->firstOrFail()->pivot->status);
        $this->assertSame(0, (int) $user->groups()->whereKey($oldGender->id)->firstOrFail()->pivot->status);
        $this->assertSame(0, (int) $user->groups()->whereKey($oldAge->id)->firstOrFail()->pivot->status);
    }

    private function enableRegistrationOnly(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => false,
        ]);
    }

    private function enableStageC(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);
    }
}
