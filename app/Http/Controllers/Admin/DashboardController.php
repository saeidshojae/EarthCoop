<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;

class DashboardController extends Controller
{
    public function index()
    {
        $groups = Group::all();
        return view('admin.dashboard', compact('groups'));
    }
}
