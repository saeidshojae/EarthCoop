<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\User;
use App\Services\LocationGovernance\LocationLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationLifecycleHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_preserves_id_and_prior_names_in_audit_metadata(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'], [
            'Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari',
        ])->last();
        $location->update(['localized_names' => ['fa' => 'ساری', 'en' => 'Sari']]);
        $actor = User::factory()->create();

        $renamed = app(LocationLifecycleService::class)->rename(
            $location,
            ['fa' => 'شهر ساری', 'en' => 'Sari City'],
            $actor
        );

        $this->assertSame($location->id, $renamed->id);
        $this->assertSame('شهر ساری', $renamed->localized_names['fa']);
        $this->assertSame('ساری', $renamed->metadata['name_history'][0]['localized_names']['fa']);
        $this->assertSame($actor->id, $renamed->metadata['name_history'][0]['actor_id']);
        $this->assertNotNull(Location::find($location->id));
    }

    public function test_merge_marks_old_location_historical_and_links_successor_without_deleting_old_id(): void
    {
        $actor = User::factory()->create();
        $old = Location::factory()->create(['canonical_name' => 'Old Quarter']);
        $successor = Location::factory()->create(['canonical_name' => 'Unified Quarter']);

        app(LocationLifecycleService::class)->supersede($old, collect([$successor]), 'merged', $actor);

        $old = $old->fresh(['outgoingRelations']);

        $this->assertSame('merged', $old->status);
        $this->assertNotNull($old->valid_to);
        $this->assertSame($old->id, Location::findOrFail($old->id)->id);
        $this->assertSame($successor->id, $old->outgoingRelations->sole()->to_location_id);
        $this->assertSame('merged', $old->outgoingRelations->sole()->relation_type);
        $this->assertSame($actor->id, $old->outgoingRelations->sole()->metadata['actor_id']);
    }

    public function test_split_records_each_successor_while_old_location_remains_queryable(): void
    {
        $actor = User::factory()->create();
        $old = Location::factory()->create(['canonical_name' => 'Old District']);
        $north = Location::factory()->create(['canonical_name' => 'North District']);
        $south = Location::factory()->create(['canonical_name' => 'South District']);

        app(LocationLifecycleService::class)->supersede($old, collect([$north, $south]), 'split', $actor);

        $old = $old->fresh(['outgoingRelations']);

        $this->assertSame('split', $old->status);
        $this->assertSame([$north->id, $south->id], $old->outgoingRelations->pluck('to_location_id')->sort()->values()->all());
        $this->assertSame($old->id, Location::findOrFail($old->id)->id);
    }
}
