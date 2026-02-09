<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'continent_id', 'country_id', 'province_id', 'county_id', 'neighborhood_id', 'section_id', 'city_id', 'region_id', 'street_id', 'alley_id', 'village_id', 'rural_id', 'status'];
    public function country() { return $this->belongsTo(Country::class, 'country_id'); }
    public function continent() { return $this->belongsTo(Continent::class); }
    public function province() { return $this->belongsTo(Province::class); }
    public function county() { return $this->belongsTo(County::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function region() { return $this->belongsTo(Region::class); }
    public function city() { return $this->belongsTo(City::class); }
    public function neighborhood() { return $this->belongsTo(Neighborhood::class); }
    public function street() { return $this->belongsTo(Street::class, 'street_id'); }
    public function alley() { return $this->belongsTo(Alley::class); }
    public function section(){ return $this->belongsTo(District::class, 'section_id'); }
    public function rural() { return $this->belongsTo(Rural::class); }

    public function village() { return $this->belongsTo(Village::class); }

}