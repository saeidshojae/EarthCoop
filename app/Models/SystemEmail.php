<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'display_name',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the default system email
     */
    public static function getDefault()
    {
        return static::where('is_default', true)
            ->where('is_active', true)
            ->first() ?? static::where('is_active', true)->first();
    }

    /**
     * Set as default email (and unset others)
     */
    public function setAsDefault()
    {
        static::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}

