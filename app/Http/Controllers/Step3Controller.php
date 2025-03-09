<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Location;

class Step3Controller extends Controller
{
    public function show()
    {
        $locations = Location::whereNull('parent_id')->with('children')->get();
        return view('auth.step3', compact('locations'));
    }

    public function store(Request $request)
    {
        $user = User::find(auth()->id());
        $user->locations()->sync($request->locations);

        return redirect('/home');
    }
}