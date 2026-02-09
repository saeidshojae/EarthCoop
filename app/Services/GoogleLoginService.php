<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GoogleLoginService
{
    public function login(array $userData)
    {
        $user = User::where('email', $userData['email']);

        Auth::login($user);
        return $user;
    }
}
