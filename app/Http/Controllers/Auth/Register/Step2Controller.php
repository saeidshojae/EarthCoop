<?php

namespace App\Http\Controllers\Auth\Register;

use App\Http\Controllers\Controller;
use App\Models\OccupationalField;
use App\Models\UserExperience;
use App\Models\ExperienceField;
use Illuminate\Http\Request;

class Step2Controller extends Controller
{
    public function show()
    {
        $checkUserHave = UserExperience::where('user_id', auth()->user()->id)->first();
        if($checkUserHave != null){
            return redirect('register/step3')->with('success', 'شما نمیتوانید به عقب برگردید اگر نیاز به ویرایش دارید ثبت نام خود را کامل کنید و از درون برنامه ویرایش را انجام دهید');
        }
        
        // دریافت فیلدهای صنفی و تخصصی بدون والد (سطح ۱)
        $occupationalFields = OccupationalField::whereNull('parent_id')->get();
        $experienceFields = ExperienceField::whereNull('parent_id')->get();
        // تمام آیتم‌ها (در هر سطح) برای نمایش در مودال
        $allOccupationalFields = OccupationalField::with('parent.parent')->get();
        $allExperienceFields = ExperienceField::with('parent.parent')->get();
        // ارسال داده‌ها به ویو
        $level1Fields = OccupationalField::whereNull('parent_id')->get();
        $level1ExperienceFields = ExperienceField::whereNull('parent_id')->get();
        
        return view('auth.register_step2', compact('occupationalFields', 'level1ExperienceFields', 'level1Fields', 'experienceFields', 'allOccupationalFields', 'allExperienceFields'));
    }

    public function getChildrenn(Request $request)
    {
        $parentId = $request->input('parent_id');
        $type     = $request->input('field_type'); // occupational / experience

        if ($type === 'occupational') {
            $children = OccupationalField::where('parent_id', $parentId)->get();
        } else {
            $children = ExperienceField::where('parent_id', $parentId)->get();
        }

        return response()->json(['data' => $children]);
    }

    public function getFieldInfo(Request $request)
    {
        $id   = $request->input('id');
        $type = $request->input('field_type');

        if ($type === 'occupational') {
            $item = OccupationalField::findOrFail($id);
        } else {
            $item = ExperienceField::findOrFail($id);
        }

        // می‌توانید هر داده‌ای که لازم دارید برگردانید؛ حداقل id و name
        return response()->json([
            'id'        => $item->id,
            'name'      => $item->name,
            'parent_id' => $item->parent_id,
        ]);
    }

    /**
     * ذخیره داده‌های ارسال شده از فرم مرحله ۲
     */
    public function store(Request $request)
    {
        // اعتبارسنجی داده‌های ورودی
        $inputs = $request->validate([
            'occupational_fields' => 'required|array',
            'occupational_fields.*' => 'exists:occupational_fields,id',
            'experience_fields' => 'required|array',
            'experience_fields.*' => 'exists:experience_fields,id',
        ]);


        // دریافت کاربر لاگین‌شده
        $user = Auth::user();

        // اگر کاربر وجود نداشت، خطا برگردانید
        if (!$user) {
            return redirect()->back()->with('error', 'کاربر یافت نشد.');
        }

        // ذخیره فیلدهای صنفی و تخصصی کاربر
        $user->occupationalFields()->sync($request->occupational_fields ?? []);
        $user->experienceFields()->sync($request->experience_fields ?? []);

        // انتقال به مرحله بعدی
        return redirect('/register/step3')->with('success', 'اطلاعات با موفقیت ذخیره شدند.');
    }
    public function addField(Request $request)
    {
        $name = $request->input('name');
        $type = $request->input('type');  // 'occupational' یا 'experience'
        $parentId = $request->input('parent_id'); // ممکن است خالی باشد

        if($parentId == 'null'){
            $parentId = null;
        }

        if ($type === 'occupational') {
            $newField = new OccupationalField();
            $newField->name = $name;
            $newField->parent_id = $parentId; // اگر parent_id خالی بود، null می‌شود (سطح 1)
            $newField->save();
        } else {
            $newField = new ExperienceField();
            $newField->name = $name;
            $newField->parent_id = $parentId;
            $newField->save();
        }
    
        return response()->json([
            'status' => 'ok',
            'id'     => $newField->id,
            'name'   => $newField->name,
            'parent_id' => $newField->parent_id,
        ]);
    }
    
    /**
     * دریافت فیلدهای فرزند به صورت Ajax
     */
    public function getChildren(Request $request)
    {
        // اعتبارسنجی اولیه داده‌های ورودی
        $request->validate([
            'parent_id' => 'required|integer',
            'field_type' => 'required|string|in:occupational,experience', // تعیین نوع فیلد
        ]);

        $parentId = $request->input('parent_id');
        $fieldType = $request->input('field_type');

        // بررسی اینکه parent_id در جدول درست وجود دارد
        if ($fieldType === 'occupational') {
            if (!OccupationalField::where('id', $parentId)->exists()) {
                return response()->json(['error' => 'شناسه صنف نامعتبر است.'], 400);
            }
            $children = OccupationalField::where('parent_id', $parentId)->get();
        } elseif ($fieldType === 'experience') {
            if (!ExperienceField::where('id', $parentId)->exists()) {
                return response()->json(['error' => 'شناسه تخصص نامعتبر است.'], 400);
            }
            $children = ExperienceField::where('parent_id', $parentId)->get();
        }

        // برگرداندن پاسخ به صورت JSON
        return response()->json(['data' => $children]);
    }
    
    public function process(Request $request)
    {
        $inputs = $request->validate([
            'occupational_fields' => 'required|array',
            'experience_fields'   => 'required|array',
        ]);

        $occupationalStatus = 1;
        $experienceStatus = 1;

        foreach($inputs['occupational_fields'] as $field_id) {
            if(OccupationalField::find(($field_id))->status == 0){
                $occupationalStatus = 0;
            }
        }
                
        foreach($inputs['experience_fields'] as $field_id) {
            if(ExperienceField::find(($field_id))->status == 0){
                $experienceStatus = 0;
            }
        }

        $user = auth()->user();

        // $user->update([
        //     'experience_status' => $experienceStatus,
        //     'occupational_status' => $occupationalStatus,
        // ]);
        
        $user->occupationalFields()->sync($request->occupational_fields);
        $user->experienceFields()->sync($request->experience_fields);

        return redirect()->route('register.step3');
    }
}

