<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionResponsibilityContractVersion extends Model
{
    public const REQUIRED_CLAUSES = [
        'role_scope_and_cycle',
        'term_compensation_and_commitment',
        'duties_reporting_and_member_oversight',
        'conflicts_confidentiality_and_vote_integrity',
        'resignation_suspension_disqualification_and_succession',
        'complaint_reply_review_and_acceptance_audit',
    ];

    protected $fillable = [
        'position', 'version', 'body', 'clause_manifest', 'e0_compliant',
        'is_active', 'published_at', 'created_by', 'change_reason',
    ];

    protected $casts = [
        'version' => 'integer',
        'clause_manifest' => 'array',
        'e0_compliant' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function hasCompleteE0Manifest(): bool
    {
        $manifest = $this->clause_manifest ?? [];
        foreach (self::REQUIRED_CLAUSES as $key) {
            if (trim((string) ($manifest[$key] ?? '')) === '') return false;
        }
        return true;
    }

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('published_at') !== null) {
                throw new LogicException('Published election contract versions are immutable.');
            }
        });

        static::deleting(function (self $model): void {
            if ($model->published_at !== null) {
                throw new LogicException('Published election contract versions cannot be deleted.');
            }
        });
    }
}
