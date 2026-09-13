<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

final class UserResidenceController extends Controller
{
    public function update(Request $request, User $user)
    {
        abort(501, 'Canonical admin residence update is not implemented yet.');
    }
}
