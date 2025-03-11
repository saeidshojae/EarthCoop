<?php

// HomeController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // دریافت گروه‌ها از کاربر احراز هویت شده
        $groups = auth()->user()->groups;
    
        // ارسال متغیر $groups به ویو
        return view('home', compact('groups'));
    }
}

