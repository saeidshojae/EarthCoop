<?php

namespace App\Contracts\Membership;

use App\Models\User;
use Illuminate\Support\Collection;

interface DimensionResolver
{
    public function dimensionKey(): string;

    public function valuesFor(User $user): Collection;
}
