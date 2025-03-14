<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvitationCode;
use Illuminate\Http\Request;

class InvitationCodeController extends Controller
{
    public function index()
    {
        $codes = InvitationCode::all();
        return view('admin.invitation_codes.index', compact('codes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:invitation_codes,code'
        ]);

        InvitationCode::create(['code' => $request->code]);

        return redirect()->route('admin.invitation_codes.index')->with('success', 'کد دعوت با موفقیت ایجاد شد.');
    }
}