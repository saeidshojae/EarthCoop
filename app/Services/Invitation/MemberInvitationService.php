<?php

namespace App\Services\Invitation;

use App\Models\InvitationCode;
use App\Models\Setting;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class MemberInvitationService
{
    public function __construct(
        private readonly CommunicationDispatcher $communications,
    ) {}

    /** @return array{code:InvitationCode,communication_id:int} */
    public function send(User $inviter, string $email): array
    {
        $hours = (int) (Setting::query()->find(1)?->expire_invation_time ?? 72);

        $code = InvitationCode::query()->create([
            'code' => Str::random(10),
            'user_id' => (int) $inviter->id,
            'expire_at' => Carbon::now()->addHours($hours),
        ]);

        $communication = $this->communications->dispatchExternal(
            'membership.member_invitation',
            ['type' => 'member_invitation_code', 'id' => (string) $code->id],
            [[
                'email' => trim($email),
                'locale' => 'fa',
            ]],
            [
                'code' => (string) $code->code,
                'expire_at' => $code->expire_at->toISOString(),
            ],
            [
                'locale' => 'fa',
                'deduplication_key' => 'membership.member_invitation:'.$code->id,
            ],
        );

        return [
            'code' => $code,
            'communication_id' => (int) $communication->id,
        ];
    }
}
