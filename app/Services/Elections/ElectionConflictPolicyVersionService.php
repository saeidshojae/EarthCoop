<?php

namespace App\Services\Elections;

use App\Models\ElectionConflictPolicyRule;
use App\Models\ElectionConflictPolicyVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ElectionConflictPolicyVersionService
{
    public function addOrReplaceRule(array $rule, User $actor, string $reason, CarbonInterface $effectiveAt): ElectionConflictPolicyVersion
    {
        if (trim($reason) === '') throw new InvalidArgumentException('Conflict policy change reason is required.');
        foreach (['current_position','new_position'] as $field) {
            if (! in_array($rule[$field] ?? null, ElectionConflictPolicyRule::POSITIONS, true)) throw new InvalidArgumentException('Invalid conflict policy position.');
        }
        foreach (['current_domain_type','new_domain_type'] as $field) {
            if (! in_array($rule[$field] ?? null, ElectionConflictPolicyRule::DOMAINS, true)) throw new InvalidArgumentException('Invalid conflict policy domain.');
        }
        if (! in_array($rule['decision'] ?? null, ElectionConflictPolicyRule::DECISIONS, true)) throw new InvalidArgumentException('Invalid conflict policy decision.');
        foreach (['current_level','new_level'] as $field) {
            if (! array_key_exists($rule[$field] ?? '', ElectionGroupDomainClassifier::LEVEL_RANK)) throw new InvalidArgumentException('Invalid conflict policy level.');
        }

        return DB::transaction(function () use ($rule, $actor, $reason, $effectiveAt): ElectionConflictPolicyVersion {
            $latest = ElectionConflictPolicyVersion::query()->orderByDesc('version')->lockForUpdate()->first();
            $currentRules = $latest?->rules()->get() ?? collect();
            $next = ElectionConflictPolicyVersion::create([
                'version' => (int) ($latest?->version ?? 0) + 1,
                'effective_at' => $effectiveAt,
                'created_by' => $actor->id,
                'change_reason' => trim($reason),
            ]);
            if ($latest !== null) {
                DB::table('election_conflict_policy_versions')->where('id', $latest->id)->update(['retired_at' => $effectiveAt, 'updated_at' => now()]);
            }

            $key = fn ($r) => implode('|', [
                $r['current_position'], $r['current_domain_type'], $r['current_level'],
                $r['new_position'], $r['new_domain_type'], $r['new_level'],
            ]);
            $replacementKey = $key($rule);
            foreach ($currentRules as $existing) {
                $data = $existing->only([
                    'current_position','current_domain_type','current_level','new_position','new_domain_type','new_level','decision','reason'
                ]);
                if ($key($data) === $replacementKey) continue;
                $next->rules()->create($data);
            }
            $next->rules()->create($rule);
            return $next->load('rules');
        }, 3);
    }
}
