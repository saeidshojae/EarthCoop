<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    protected $fillable = ['group_type', 'name', 'location_level', 'is_open', 'address_id', 'specialty_id', 'experience_id', 'age_group_id', 'gender', 'age_group_title', 'description', 'avatar', 'last_activity_at'];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        $avatar = trim((string) ($this->attributes['avatar'] ?? ''));
        if ($avatar === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $avatar) || str_starts_with($avatar, 'data:')) {
            return $avatar;
        }

        $path = ltrim(str_replace('\\', '/', $avatar), '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        if (str_starts_with($path, 'images/groups/')) {
            return asset($path);
        }

        if (str_contains($path, '/')) {
            return asset($path);
        }

        return asset('images/groups/' . $path);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->where('users.is_system', false)
            ->withPivot(
            'role', 'status', 'expired', 'last_read_message_id',
            'role_override_active', 'role_override_original_role', 'role_override_started_at',
            'role_override_expires_at', 'role_override_changed_by', 'role_override_source'
        )->withTimestamps();
    }

    public function systemUsers()
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->where('users.is_system', true)
            ->withTimestamps();
    }


    public function specialty()
    {
        return $this->belongsTo(OccupationalField::class, 'specialty_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function experience()
    {
        return $this->belongsTo(ExperienceField::class, 'experience_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function ageGroup(){
        return $this->belongsTo(AgeGroup::class, 'age_group_id');
    }

    public function gender(){
        return $this->gender == 'male' ? 'مرد' : 'زن';
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function userCount(){
        // The group-chat panel preloads this aggregate for all related groups in
        // one query. Reuse it when present and preserve the canonical fallback
        // everywhere else.
        if (array_key_exists('active_members_count', $this->attributes)) {
            return (int) $this->attributes['active_members_count'];
        }

        return $this->hasMany(GroupUser::class)
            ->whereHas('user', fn ($query) => $query->where('is_system', false))
            ->where('status', 1)
            ->where('role', '!=', 4)
            ->count();
    }
    public function guestsCount(){
        if (array_key_exists('active_guests_count', $this->attributes)) {
            return (int) $this->attributes['active_guests_count'];
        }

        return $this->hasMany(GroupUser::class)
            ->whereHas('user', fn ($query) => $query->where('is_system', false))
            ->where('status', 1)
            ->where('role', 4)
            ->count();
    }

    public function groupUser(){
        return $this->hasMany(GroupUser::class);
    }

    public function polls(){
        return $this->hasMany(Poll::class);
    }

    public function feedItems()
    {
        return $this->hasMany(GroupFeedItem::class)->orderBy('sequence');
    }

    public function blogs(){
        return $this->hasMany(Blog::class, 'group_id');
    }

    public function elections(){
        return $this->hasMany(Election::class, 'group_id');
    }

    public function sessions()
    {
        return $this->hasMany(GroupSession::class);
    }

    public function updateLastActivity()
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * پروژه‌های نجم بهار (به عنوان صاحب پروژه)
     */
    public function najmBaharProjects()
    {
        return $this->morphMany(\App\Modules\NajmBahar\Models\Project::class, 'owner');
    }

    /**
     * سرمایه‌گذاری‌های نجم بهار (به عنوان سرمایه‌گذار)
     */
    public function najmBaharInvestments()
    {
        return $this->morphMany(\App\Modules\NajmBahar\Models\Investment::class, 'investor');
    }
}
