<?php

namespace App\Models;

use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ElectionResponsibilityOffer extends Model
{
    protected $fillable = [
        'election_id', 'candidate_user_id', 'position', 'ranking_position',
        'contract_version_id', 'status', 'offered_at', 'expires_at', 'responded_at',
        'eligibility_checked_at', 'resolution_reason', 'response_metadata',
    ];

    protected $casts = [
        'ranking_position' => 'integer',
        'status' => ElectionResponsibilityOfferStatus::class,
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'eligibility_checked_at' => 'datetime',
        'response_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $offer): void {
            $status = $offer->status instanceof ElectionResponsibilityOfferStatus ? $offer->status : ElectionResponsibilityOfferStatus::from((string) $offer->status);
            if ($status !== ElectionResponsibilityOfferStatus::Pending) return;
            $contract = ElectionResponsibilityContractVersion::query()->find($offer->contract_version_id);
            if (! $contract || ! $contract->e0_compliant || ! $contract->hasCompleteE0Manifest() || $contract->published_at === null) {
                throw new RuntimeException('A pending election responsibility offer requires a published E0-compliant contract version.');
            }
        });

        static::updating(function (self $offer): void {
            if (! $offer->isDirty('status')) return;
            $status = $offer->status instanceof ElectionResponsibilityOfferStatus ? $offer->status : ElectionResponsibilityOfferStatus::from((string) $offer->status);
            if ($status !== ElectionResponsibilityOfferStatus::Accepted) return;
            $evidence = ($offer->response_metadata ?? [])['acceptance_evidence'] ?? null;
            if (! is_array($evidence)
                || (int) ($evidence['candidate_user_id'] ?? 0) !== (int) $offer->candidate_user_id
                || (int) ($evidence['contract_version_id'] ?? 0) !== (int) $offer->contract_version_id
                || empty($evidence['confirmed_at'])) {
                throw new RuntimeException('Accepted responsibility offers require explicit, version-bound acceptance evidence.');
            }
        });
    }

    public function election() { return $this->belongsTo(Election::class); }
    public function candidateUser() { return $this->belongsTo(User::class, 'candidate_user_id'); }
    public function contractVersion() { return $this->belongsTo(ElectionResponsibilityContractVersion::class, 'contract_version_id'); }
}
