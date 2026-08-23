<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupSetting extends Model
{
    use HasFactory;

    protected $table = 'group_setting';
    protected $fillable = [
        'level', 'inspector_count', 'manager_count', 'election_time',
        'max_for_election', 'election_status', 'second_election_time',
        'election_report_min_distinct_voters', 'election_report_bucket_days',
        'election_meaningful_trend_min_net_change',
    ];

    protected $casts = [
        'election_report_min_distinct_voters' => 'integer',
        'election_report_bucket_days' => 'integer',
        'election_meaningful_trend_min_net_change' => 'integer',
    ];
    
public function name()
{
    $baseLevels = [
        'global' => 'جهانی',
        'continent' => 'قاره',
        'country' => 'کشور',
        'province' => 'استان',
        'county' => 'شهرستان',
        'district' => 'بخش',
        'city' => 'شهر/دهستان',
        'region' => 'منطقه/روستا',
        'street' => 'خیابان',
        'alley' => 'کوچه',
        'neighborhood' => 'محله',
    ];

    $suffixes = [
        'gender' => 'جنسیتی',
        'age' => 'سنی',
        'job' => 'صنفی/شغلی',
        'experience' => 'علمی/تجربی',
    ];

    foreach ($suffixes as $key => $suffixLabel) {
        if (str_ends_with($this->level, "_$key")) {
            $base = str_replace("_$key", '', $this->level);
            $baseName = $baseLevels[$base] ?? $base;
            return $baseName . ' - ' . $suffixLabel;
        }
    }

    return $baseLevels[$this->level] ?? $this->level;
}

}
