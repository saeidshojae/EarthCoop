<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CommunityStory extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'user_id',
        'body',
        'display_name',
        'show_name',
        'avatar_path',
        'show_avatar',
        'role',
        'location',
        'locale',
        'status',
        'consent_publication_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'is_featured',
        'published_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'show_name' => 'boolean',
        'show_avatar' => 'boolean',
        'is_featured' => 'boolean',
        'consent_publication_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function welcomeFeatured(int $limit = 3): Collection
    {
        return static::query()
            ->where('status', self::STATUS_APPROVED)
            ->whereNotNull('consent_publication_at')
            ->where('is_featured', true)
            ->whereNotNull('published_at')
            ->whereNull('withdrawn_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 3)))
            ->get();
    }
}
