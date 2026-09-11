<?php

namespace App\Services\Membership;

use App\Data\Membership\MembershipResolution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipAuditService
{
    public function record(User $user, MembershipResolution $resolution): void
    {
        DB::table('membership_resolution_audits')->insert([
            'user_id' => $user->getKey(),
            'fingerprint' => $resolution->auditFingerprint,
            'resolution' => json_encode($resolution->canonical(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
