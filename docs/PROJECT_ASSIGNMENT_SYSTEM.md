# سامانه ارجاع پروژه‌ها برای بررسی
## Project Assignment/Referral System

**تاریخ: 16 فوریه 2026**
**نسخه: 1.0**

---

## خلاصه ویژگی

سامانه ارجاع پروژه‌ها امکان می‌دهد که مدیران ادمین پروژه‌های در حال بررسی را برای بررسی تخصصی به کاربران فردی یا گروه‌های تخصص ارجاع دهند.

---

## اجزای پیاده‌سازی شده

### 1. مهاجرت پایگاه داده
**فایل:** `database/migrations/2026_02_16_000001_add_assignment_to_najm_bahar_projects_table.php`

فیلدهای جدید اضافه شده به جدول `najm_bahar_projects`:
- `assigned_to_type` - نوع مقصد (User یا Group)
- `assigned_to_id` - شناسه مقصد
- `assigned_at` - تاریخ ارجاع
- `assignment_note` - توضیحات ارجاع (چرا و برای چه)
- `assignment_status` - وضعیت بررسی (pending/under_review/completed/rejected)
- `assignment_review_note` - نظر بررسی کننده
- `assignment_completed_at` - تاریخ اتمام بررسی

### 2. مدل (Model)
**فایل:** `app/Modules/NajmBahar/Models/Project.php`

اضافات:
- فیلدهای assignment را به `$fillable` اضافه شده
- Casts برای التاریخ‌های `assigned_at` و `assignment_completed_at`
- رابطه `assignedTo()` - Polymorphic relation برای دسترسی به User یا Group

```php
public function assignedTo()
{
    return $this->morphTo('assigned_to');
}
```

### 3. سرویس (ProjectService)
**فایل:** `app/Modules/NajmBahar/Services/ProjectService.php`

دو متد جدید اضافه شده:

#### `assignProjectToReviewer($project, $type, $targetId, $note)`
- ارجاع پروژه به کاربر یا گروه
- اعتبارسنجی نوع و وجود مقصد
- ثبت در تاریخچه (ProjectReview)
- ارسال اطلاع‌رسانی به مقصد
- برای گروه‌ها: اطلاع تمام اعضا

```php
$service->assignProjectToReviewer($project, 'User', 5, 'نیاز به تایید از متخصص دارد');
```

#### `updateAssignmentReview($project, $status, $reviewNote)`
- بروزرسانی نتیجه بررسی ارجاع شده
- وضعیت: completed یا rejected
- ثبت نظر بررسی کننده

### 4. کنترلر (AdminProjectController)
**فایل:** `app/Http/Controllers/Admin/NajmBahar/ProjectController.php`

اضافات:
- `assign()` - ارجاع پروژه
- `updateAssignmentReview()` - بروزرسانی نتیجه بررسی
- `getUsers()` - API برای دریافت لیست کاربران (Admin/Specialist)
- `getGroups()` - API برای دریافت لیست گروه‌ها

### 5. اطلاع‌رسانی
**فایل:** `app/Notifications/NajmBahar/ProjectAssigned.php`

اطلاع‌رسانی برای پروژه‌های ارجاع شده:
- ارسال ایمیل با جزئیات پروژه
- ثبت در دیتابیس (نوتیفیکیشن)
- Broadcast برای بروزرسانی فوری

متغیرهای ایمیل:
- نام دریافت کننده
- نام پروژه
- دسته‌بندی
- سرمایه مورد نیاز
- توضیحات ارجاع
- لینک برای مشاهده جزئیات

### 6. رابط کاربری (Views)
**فایل:** `resources/views/admin/najm-bahar/projects/show.blade.php`

تغییرات:

#### دکمه ارجاع
```blade
<button type="button" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg" 
    onclick="document.getElementById('assignForm').classList.remove('hidden')">
    ارجاع برای بررسی
</button>
```

#### فرم ارجاع
```blade
<div id="assignForm" class="bg-white rounded-lg shadow p-6 mb-6 hidden">
    <h2 class="text-lg font-bold text-gray-900 mb-3">ارجاع پروژه برای بررسی</h2>
    <form method="POST" action="{{ route('admin.najm-bahar.projects.assign', $project) }}">
        @csrf
        <!-- نوع مقصد -->
        <select name="assigned_to_type" id="assignedToType" onchange="updateAssigneeList()">
            <option value="">انتخاب کنید...</option>
            <option value="User">کاربر</option>
            <option value="Group">گروه</option>
        </select>
        
        <!-- انتخاب مقصد (بارگذاری ديناميکی) -->
        <select name="assigned_to_id" id="assignedToId" disabled>
            <option value="">ابتدا نوع مقصد را انتخاب کنید</option>
        </select>
        
        <!-- توضیحات -->
        <textarea name="assignment_note" 
                  placeholder="مثلاً: این پروژه نیاز به تخصص در زمینه فناوری دارد">
        </textarea>
    </form>
</div>
```

#### نمایش وضعیت ارجاع
```blade
<div class="bg-blue-50 rounded-lg shadow p-6 border-r-4 border-blue-600">
    <h2 class="text-lg font-bold text-blue-900 mb-4">وضعیت ارجاع</h2>
    <!-- ارجاع شده به -->
    <!-- تاریخ -->
    <!-- وضعیت (در انتظار/تحت بررسی/تکمیل/رد) -->
    <!-- توضیحات ارجاع -->
    <!-- نظر بررسی کننده (در صورت تکمیل یا رد) -->
</div>
```

#### JavaScript برای بارگذاری دینامیکی
```javascript
async function updateAssigneeList() {
    const type = document.getElementById('assignedToType').value;
    const endpoint = type === 'User' 
        ? '{{ route("admin.najm-bahar.get-users") }}'
        : '{{ route("admin.najm-bahar.get-groups") }}';
    
    const response = await fetch(endpoint);
    const data = await response.json();
    
    // بارگذاری انتخاب‌ها
}
```

### 7. مسیرها (Routes)
**فایل:** `routes/najm-bahar.php`

اضافات:
```php
Route::post('/{project}/assign', [AdminProjectController::class, 'assign'])
    ->name('assign');
Route::post('/{project}/update-assignment-review', [AdminProjectController::class, 'updateAssignmentReview'])
    ->name('update-assignment-review');

// API Endpoints
Route::get('/get-users', [AdminProjectController::class, 'getUsers'])
    ->name('get-users');
Route::get('/get-groups', [AdminProjectController::class, 'getGroups'])
    ->name('get-groups');
```

---

## جریان کاری (Workflow)

```
پروژه: Pending/Under_Review
         ↓
[ادمین کلیک روی "ارجاع برای بررسی"]
         ↓
[انتخاب نوع (User/Group) و مقصد]
         ↓
[ثبت توضیحات (اختیاری)]
         ↓
POST /admin/najm-bahar/projects/{id}/assign
         ↓
[بروزرسانی: assigned_to_type, assigned_to_id, assigned_at, assignment_status=pending]
         ↓
[ثبت در ProjectReview با action='assigned']
         ↓
[ارسال ایمیل و نوتیفیکیشن]
         ↓
پروژه: ارجاع شده (marked with assignment info)
```

---

## نمونه‌های استفاده

### ارجاع به کاربر
```php
$service->assignProjectToReviewer(
    $project,
    'User',
    5,
    'لطفاً از دیدگاه فنی ارزیابی کنید'
);
```

### ارجاع به گروه
```php
$service->assignProjectToReviewer(
    $project,
    'Group',
    3,
    'نیاز به نظر کمیته نظارت دارد'
);
```

### بروزرسانی نتیجه
```php
$service->updateAssignmentReview(
    $project,
    'completed',
    'پروژه از لحاظ فنی کامل و قابل تایید است'
);
```

---

## قابلیت‌های اضافی

### 1. فیلتر کردن بر اساس وضعیت ارجاع
```php
$assignedProjects = Project::where('assigned_to_type', 'User')
    ->where('assigned_to_id', auth()->id())
    ->where('assignment_status', 'pending')
    ->get();
```

### 2. نمایش پروژه‌های ارجاع شده برای کاربر
در داشبورد کاربر یا صفحه جداگانه می‌توان نمایش داد

### 3. تاریخچه ارجاعات
تمام ارجاعات در جدول `najm_bahar_project_reviews` ثبت می‌شود

---

## اعتبارسنجی و کنترل‌های امنیتی

✅ **ادمین تنها می‌تواند:**
- پروژه‌های pending یا under_review را ارجاع دهد
- به کاربران admin یا specialist ارجاع دهد
- به گروه‌های موجود ارجاع دهد

✅ **سطحهای سیاق‌مند:**
- اگر Group: اطلاع تمام اعضای گروه
- اگر User: اطلاع مستقیم کاربر

✅ **ثبت کاملی:**
- تمام ارجاعات در `ProjectReview` ثبت می‌شود
- توضیحات محفوظ می‌ماند
- قابل ردگیری

---

## تست‌های توصیه‌شده

1. **ارجاع به کاربر:**
   - انتخاب کاربر
   - بررسی ایمیل دریافتی
   - بررسی نوتیفیکیشن

2. **ارجاع به گروه:**
   - انتخاب گروه
   - بررسی اطلاع تمام اعضا

3. **ایجاد نتیجه:**
   - بروزرسانی وضعیت
   - ثبت نظر

4. **تاریخچه:**
   - بررسی ثبت اقدامات در `ProjectReview`

---

## اطلاعات فنی اضافی

### Collections:
- فیلدهای Assignment Nullable
- Status Enum: pending, under_review, completed, rejected

### Broadcasting:
- اطلاعات ارجاع Broadcast می‌شود برای بروزرسانی فوری

### Queue Jobs:
- اطلاع‌رسانی‌ها از طریق ShouldQueue اجرا می‌شوند

---

## توسعه‌های آینده

1. **Comments برای Reviews:**
   - نظرات متعدد از بررسی‌کنندگان
   - Conversation thread

2. **Deadline برای Reviews:**
   - تاریخ مقررسازی برای تکمیل بررسی

3. **Scoring/Rating:**
   - امتیازدهی پروژه توسط بررسی‌کنندگان

4. **Analytics:**
   - زمان میانگین بررسی
   - موفقیت‌های بررسی

---

**نویسنده:** سامانه
**تاریخ:** 16 فوریه 2026
**وضعیت:** ✅ فعال و آماده
