<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OccupationController extends Controller
{
    public function index()
    {
        // کد مربوط به نمایش لیست صنف‌ها
    }

    public function create()
    {
        // کد مربوط به نمایش فرم ایجاد صنف جدید
    }

    public function store(Request $request)
    {
        // کد مربوط به ذخیره صنف جدید
    }

    public function show($id)
    {
        // کد مربوط به نمایش جزئیات یک صنف
    }

    public function edit($id)
    {
        // کد مربوط به نمایش فرم ویرایش صنف
    }

    public function update(Request $request, $id)
    {
        // کد مربوط به به‌روزرسانی صنف
    }

    public function destroy($id)
    {
        // کد مربوط به حذف صنف
    }
}