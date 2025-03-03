<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Specialization;

class SpecializationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // لیست تمام تخصص‌ها
        $specializations = Specialization::all();
        return view('specializations.index', compact('specializations'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // نمایش فرم ایجاد تخصص جدید
        return view('specializations.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // اعتبارسنجی داده‌ها
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:specializations,id',
            'job_field_id' => 'required|exists:job_fields,id',
            'level' => 'required|integer|min:1',
        ]);

        // ذخیره اطلاعات تخصص جدید
        Specialization::create($request->all());

        return redirect()->route('specializations.index')->with('success', 'تخصص جدید با موفقیت اضافه شد.');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // نمایش جزئیات یک تخصص خاص
        $specialization = Specialization::findOrFail($id);
        return view('specializations.show', compact('specialization'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // نمایش فرم ویرایش تخصص
        $specialization = Specialization::findOrFail($id);
        return view('specializations.edit', compact('specialization'));
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
        // اعتبارسنجی داده‌ها
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:specializations,id',
            'job_field_id' => 'required|exists:job_fields,id',
            'level' => 'required|integer|min:1',
        ]);

        // به‌روزرسانی تخصص
        $specialization = Specialization::findOrFail($id);
        $specialization->update($request->all());

        return redirect()->route('specializations.index')->with('success', 'تخصص با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // حذف تخصص
        $specialization = Specialization::findOrFail($id);
        $specialization->delete();

        return redirect()->route('specializations.index')->with('success', 'تخصص با موفقیت حذف شد.');
    }

    /**
     * Get subcategories of the specified parent.
     *
     * @param  int  $parentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubcategories($parentId)
    {
        // واکشی زیرشاخه‌های تخصص
        $subcategories = Specialization::where('parent_id', $parentId)->select('id', 'title')->get();

        if ($subcategories->isEmpty()) {
            return response()->json(['message' => 'زیرشاخه‌ای یافت نشد.'], 404);
        }

        return response()->json($subcategories);
    }
}
