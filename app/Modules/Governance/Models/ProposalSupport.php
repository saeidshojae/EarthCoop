<?php

namespace App\Modules\Governance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProposalSupport extends Model
{
    protected $table = 'governance_proposal_supports';

    protected $fillable = ['proposal_id', 'user_id', 'source', 'source_reference', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function proposal() { return $this->belongsTo(Proposal::class); }
    public function user() { return $this->belongsTo(User::class); }
}
