<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index() {
        $user = auth()->user();
        $groups = $user->groups;
        return view('home', compact('groups'));
    }
}
