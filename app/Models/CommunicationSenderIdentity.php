<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationSenderIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'email', 'display_name', 'reply_to', 'purpose', 'system_identity_key',
        'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function templateVersions(): HasMany
    {
        return $this->hasMany(CommunicationTemplateVersion::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CommunicationRule::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class);
    }
}
