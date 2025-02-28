<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\JobField;
use App\Models\Specialization;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::with(['jobFields', 'specializations'])->get();
        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $jobFields = JobField::all();
        $specializations = Specialization::all();
        return view('users.create', compact('jobFields', 'specializations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'nationality' => 'required',
            'national_id' => 'required|unique:users',
            'phone' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'password' => 'required|min:8',
            'job_fields' => 'nullable|array',
            'specializations' => 'nullable|array',
        ]);

        $user = User::create($validated);

        // اتصال رسته‌های صنفی و تخصص‌ها
        $user->jobFields()->sync($request->job_fields);
        $user->specializations()->sync($request->specializations);

        return redirect()->route('users.index')->with('success', 'کاربر با موفقیت ایجاد شد.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}