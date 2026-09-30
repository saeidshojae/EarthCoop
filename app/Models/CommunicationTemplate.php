<?php

namespace App\Models;

use App\Enums\Communication\CommunicationClassification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'category', 'classification', 'is_active'];

    protected function casts(): array
    {
        return [
            'classification' => CommunicationClassification::class,
            'is_active' => 'boolean',
        ];
    }

    public function versions(): HasMany
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
