<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionVoteFeedback extends Model
{
    protected $table = 'election_vote_feedback';

    protected $fillable = [
        'election_id', 'ballot_event_id', 'author_user_id', 'subject_user_id',
        'event_type', 'visibility', 'anonymous', 'body', 'moderation_status',
        'moderation_reasons', 'moderation_source', 'moderated_at', 'reviewed_by',
        'reviewed_at', 'published_at', 'public_bucket_start',
    ];

    protected $casts = [
        'anonymous' => 'boolean',
        'moderation_reasons' => 'array',
        'moderated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'public_bucket_start' => 'date',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function ballotEvent() { return $this->belongsTo(ElectionBallotEvent::class, 'ballot_event_id'); }
    public function author() { return $this->belongsTo(User::class, 'author_user_id'); }
    public function subject() { return $this->belongsTo(User::class, 'subject_user_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
