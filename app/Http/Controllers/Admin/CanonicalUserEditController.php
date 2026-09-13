<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

final class CanonicalUserEditController extends Controller
{
    public function __invoke(User $user)
    {
        if (! (bool) config('location-governance.registration_enabled')) {
            return app(UserController::class)->edit($user);
        }

        return view('admin.user.edit_canonical', compact('user'));
    }
}
