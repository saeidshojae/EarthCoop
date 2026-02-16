<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    
    protected $fillable = [
        'email', 'phone', 'password', 'fingerprint_id', 'terms_accepted_at', 'status',
        'first_name', 'last_name', 'birth_date', 'gender', 'nationality', 'national_id', 'show_national_id', 'show_gender', 'avatar', 'show_birthdate', 'show_phone', 'show_email', 'show_name', 'biografie', 'social_networks', 'show_biografie', 'show_social_networks', 'show_documents', 'documents', 'experience_status', 'occupational_status', 'last_seen', 'show_groups', 'show_created_at', 'email_verified_at', 'edited', 'last_login_ip', 'last_login_at'
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'show_name' => 'boolean',
        'show_email' => 'boolean',
        'show_phone' => 'boolean',
        'show_birthdate' => 'boolean',
        'show_gender' => 'boolean',
        'show_national_id' => 'boolean',
        'show_biografie' => 'boolean',
        'show_documents' => 'boolean',
        'show_groups' => 'boolean',
        'show_created_at' => 'boolean',
        'show_social_networks' => 'boolean',
    ];

    protected $dates = [
        'last_seen',
        'birth_date',
        'terms_accepted_at',
    ];

    // روابط چند به چند:
    public function occupationalFields()
    {
        return $this->belongsToMany(OccupationalField::class, 'user_occupational_field');
    }

    public function experienceFields()
    {
        return $this->belongsToMany(ExperienceField::class, 'user_experience_field');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'user_location');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_user', 'user_id', 'group_id')
            ->withPivot('role', 'status', 'expired', 'last_read_message_id')
            ->withTimestamps();
    }    

    public function messages()
    {
        return $this->hasMany(Message::class, 'user_id');
    }

    public function fullName()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function specialties()
    {
        return $this->belongsToMany(OccupationalField::class, 'user_occupational_field', 'user_id', 'occupational_field_id');
    }

    public function experiences()
    {
        return $this->belongsToMany(ExperienceField::class, 'user_experience_field', 'user_id', 'experience_field_id');
    }

    public function address()
    {
        return $this->hasOne(Address::class);
    }
    
    public function groupUser()
    {
        return $this->hasMany(GroupUser::class);
    }

    public function notificationSettings()
    {
        return $this->hasOne(NotificationSetting::class);
    }

    public function points()
    {
        return $this->hasOne(UserPoint::class);
    }

    public function profile(){
        if($this->avatar == null){
            $fColor = rand(1, 255);
            $sColor = rand(1, 255);
            $tColor = rand(1, 255);

            return '<div class="group-avatar" style="width: 5rem; height: 5rem; font-size: 2rem; margin: 0; background-color: rgba(' . $fColor . ', ' . $sColor . ', ' . $tColor . ', .1); color: rgb(' . $fColor . ', ' . $sColor . ', ' . $tColor . ');">
                        <span>' . strtoupper(substr($this->email, 0, 1)) . '</span>
                    </div>';
        }else{
            return '<img alt="تصویر پروفایل" class="rounded-circle" width="150" height="150" src=' . asset('/images/users/avatars/' . $this->avatar) . '>';
        }
    }


    public function profileInChat(){
        if($this->avatar == null){
            return '<div class="group-avatar" style="width: 2rem; height: 2rem; font-size: .6rem; margin: 0; background-color: #e3f2fd; color: #1976d2;">
                        <span>' . mb_substr($this->first_name, 0, 1) . ' ' . mb_substr($this->last_name, 0, 1) . '</span>
                    </div>';
        }else{
            return '<img alt="تصویر پروفایل" class="rounded-circle" width="32" height="32" src=' . asset('/images/users/avatars/' . $this->avatar) . '>';
        }
    }

    public function isOnline()
    {
        if (!$this->last_seen || !auth()->check() || auth()->id() !== $this->id) {
            return false;
        }
        return $this->last_seen->diffInMinutes(now()) < 5;
    }

    /**
     * روابط با Role ها
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * بررسی اینکه آیا کاربر دارای نقش خاصی است
     */
    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles()->where('slug', $role)->exists();
        }
        
        return $this->roles->contains($role);
    }

    /**
     * بررسی اینکه آیا کاربر دارای دسترسی خاصی است
     */
    public function hasPermission($permission)
    {
        // اگر Super Admin است، همه دسترسی‌ها را دارد
        if ($this->is_admin || $this->hasRole('super-admin')) {
            return true;
        }

        // بررسی دسترسی از طریق نقش‌ها
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * بررسی اینکه آیا کاربر دارای هر کدام از دسترسی‌های داده شده است
     */
    public function hasAnyPermission(array $permissions)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * بررسی اینکه آیا کاربر دارای همه دسترسی‌های داده شده است
     */
    public function hasAllPermissions(array $permissions)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * اضافه کردن نقش به کاربر
     */
    public function assignRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first();
        }
        
        if ($role && !$this->hasRole($role)) {
            $this->roles()->attach($role);
        }
        
        return $this;
    }

    /**
     * حذف نقش از کاربر
     */
    public function removeRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->first();
        }
        
        if ($role) {
            $this->roles()->detach($role);
        }
        
        return $this;
    }

    /**
     * همگام‌سازی نقش‌ها
     */
    public function syncRoles(array $roles)
    {
        $roleIds = [];
        
        foreach ($roles as $role) {
            if (is_string($role)) {
                $r = Role::where('slug', $role)->first();
                if ($r) {
                    $roleIds[] = $r->id;
                }
            } else {
                $roleIds[] = $role;
            }
        }
        
        $this->roles()->sync($roleIds);
        
        return $this;
    }

    /**
     * دریافت همه دسترسی‌های کاربر (از طریق نقش‌ها)
     */
    public function getAllPermissions()
    {
        $permissions = collect();
        
        foreach ($this->roles as $role) {
            $permissions = $permissions->merge($role->permissions);
        }
        
        return $permissions->unique('id');
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
