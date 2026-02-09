<?php

namespace App\Modules\NajmBahar\Services;

class AccountNumberService
{
    /**
     * Build user or entity main account number according to the rules provided.
     * - system main account: 0000000000
     * - legal entities: 0000000001 - 0999999999
     * - bank central: 1000000000
     * - users: 1000000001 - 9999999999
     * For users we expect the caller to pass the user id and we'll compose: 1000000xxx
     */
    public static function makeMainAccountNumberForUser(int $userId): string
    {
        // ensure 10 digits: prefix 1000000000 + userId
        $base = 1000000000;
        $num = $base + $userId;
        return (string) $num;
    }

    public static function makeLegalEntityAccountNumberForGroup(int $groupId): string
    {
        if ($groupId < 1 || $groupId > 999999999) {
            throw new \InvalidArgumentException('Group id is out of legal entity range.');
        }

        return str_pad((string) $groupId, 10, '0', STR_PAD_LEFT);
    }

    public static function makeSystemAccountNumber(): string
    {
        return '0000000000';
    }

    public static function makeSubAccountCode(string $mainAccountNumber, int $subIndex): string
    {
        $sub = str_pad((string)$subIndex, 3, '0', STR_PAD_LEFT);
        return $mainAccountNumber . '-' . $sub;
    }
}
