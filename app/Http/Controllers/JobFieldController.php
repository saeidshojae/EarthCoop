<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobField;

class JobFieldController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // نمایش لیست کامل رسته‌های صنفی
        $jobFields = JobField::all();
        return view('jobFields.index', compact('jobFields'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // نمایش فرم ایجاد رسته جدید
        return view('jobFields.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // اعتبارسنجی ورودی‌ها
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:job_fields,id',
            'level' => 'required|integer|min:1',
        ]);

        // ذخیره اطلاعات در دیتابیس
        JobField::create($request->all());

        return redirect()->route('job-fields.index')->with('success', 'رسته جدید با موفقیت اضافه شد.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // نمایش جزئیات رسته
        $jobField = JobField::findOrFail($id);
        return view('jobFields.show', compact('jobField'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // نمایش فرم ویرایش
        $jobField = JobField::findOrFail($id);
        return view('jobFields.edit', compact('jobField'));
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
        // اعتبارسنجی ورودی‌ها
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:job_fields,id',
            'level' => 'required|integer|min:1',
        ]);

        // به‌روزرسانی اطلاعات
        $jobField = JobField::findOrFail($id);
        $jobField->update($request->all());

        return redirect()->route('job-fields.index')->with('success', 'رسته با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // حذف رسته
        $jobField = JobField::findOrFail($id);
        $jobField->delete();

        return redirect()->route('job-fields.index')->with('success', 'رسته با موفقیت حذف شد.');
    }

    /**
     * Get subcategories of the specified parent.
     *
     * @param  int  $parentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubcategories($parentId)
    {
        // واکشی زیرشاخه‌ها
        $subcategories = JobField::where('parent_id', $parentId)->select('id', 'title')->get();

        if ($subcategories->isEmpty()) {
            return response()->json(['message' => 'زیرشاخه‌ای یافت نشد.'], 404);
        }

        return response()->json($subcategories);
    }
}
