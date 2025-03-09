<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;

class Step1Controller extends Controller
{
    public function show()
    {
        return view('auth.step1');
    }

    public function store(Request $request)
    {
        $user = User::find(auth()->id());
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'birth_date' => $request->birth_date,
            'gender' => $request->gender,
            'nationality' => $request->nationality,
            'national_id' => $request->national_id,
            'phone_number' => $request->phone_number,
        ]);

        return redirect('/step2');
    }
}