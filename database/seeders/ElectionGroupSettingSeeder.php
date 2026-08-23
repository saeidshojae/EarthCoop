<?php

namespace Database\Seeders;

use App\Models\GroupSetting;
use Illuminate\Database\Seeder;

class ElectionGroupSettingSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            'global', 'continent', 'country', 'province', 'county', 'district',
            'city', 'region', 'neighborhood', 'street', 'alley',
        ];

        $suffixes = ['', '_experience', '_job', '_age', '_gender'];

        foreach ($levels as $level) {
            foreach ($suffixes as $suffix) {
                GroupSetting::query()->updateOrCreate(
                    ['level' => $level.$suffix],
                    [
                        'manager_count' => 7,
                        'inspector_count' => 3,
                        'election_time' => 30,
                        'max_for_election' => 20,
                        'election_status' => 1,
                        'second_election_time' => 6,
                        'election_report_min_distinct_voters' => 10,
                        'election_report_bucket_days' => 7,
                        'election_meaningful_trend_min_net_change' => 3,
                    ],
                );
            }
        }
    }
}
