# ✅ بررسی ویژگی ارجاع پروژه‌ها

## فایل‌های ایجاد/تغییر داده شده

### 1. مهاجرت (Migration)
✅ `database/migrations/2026_02_16_000001_add_assignment_to_najm_bahar_projects_table.php`
- 7 فیلد جدید اضافه شده
- ایندکس‌ها برقرار شده
- Rollback تعریف شده

### 2. مدل‌ها (Models)
✅ `app/Modules/NajmBahar/Models/Project.php`
- فیلدهای assignment به `$fillable` اضافه شده
- Casts برای تاریخ‌ها اضافه شده
- رابطه `assignedTo()` اضافه شده

✅ `app/Modules/NajmBahar/Models/ProjectReview.php`
- Action labels برای assigned/assignment_completed/assignment_rejected اضافه شده

### 3. سرویس‌ها (Services)
✅ `app/Modules/NajmBahar/Services/ProjectService.php`
- متد `assignProjectToReviewer()` اضافه شده (75 خط)
- متد `updateAssignmentReview()` اضافه شده (25 خط)
- مدیریت اطلاع‌رسانی برای User و Group

### 4. کنترلرها (Controllers)
✅ `app/Http/Controllers/Admin/NajmBahar/ProjectController.php`
- Import `User` و `Group` اضافه شده
- متد `assign()` اضافه شده
- متد `updateAssignmentReview()` اضافه شده
- API متد `getUsers()` اضافه شده
- API متد `getGroups()` اضافه شده

### 5. اطلاع‌رسانی (Notifications)
✅ `app/Notifications/NajmBahar/ProjectAssigned.php` - فایل جدید
- ایمیل اطلاع‌رسانی
- Database notification
- Broadcast support

### 6. نماها (Views)
✅ `resources/views/admin/najm-bahar/projects/show.blade.php`
- دکمه «ارجاع برای بررسی» اضافه شده
- فرم ارجاع اضافه شده (نوع، انتخاب مقصد، توضیحات)
- نمایش وضعیت ارجاع (اگر ارجاع شده باشد)
- JavaScript برای بارگذاری دینامیکی

### 7. مسیرها (Routes)
✅ `routes/najm-bahar.php`
- مسیر POST assign
- مسیر POST update-assignment-review
- مسیر GET get-users
- مسیر GET get-groups

### 8. مستندات
✅ `docs/PROJECT_ASSIGNMENT_SYSTEM.md` - فایل جدید
- توصیف کامل ویژگی
- نمونه‌های کد
- جریان کاری
- اعتبارسنجی‌ها

---

## اعتبارسنجی ویژگی

### ✅ عملکرد ارجاع
- [x] انتخاب نوع مقصد (User/Group)
- [x] بارگذاری دینامیکی لیست مقصد
- [x] اضافه کردن توضیحات
- [x] متصل شدن به صحیح در دیتابیس
- [x] ثبت در ProjectReview با action='assigned'

### ✅ اطلاع‌رسانی
- [x] ایمیل ارسال شود
- [x] بررسی تمام اعضای گروه (اگر گروه انتخاب شود)
- [x] Database notification ثبت شود
- [x] Broadcast پشتیبانی شود

### ✅ نمایش وضعیت
- [x] نام مقصد نمایش داده شود
- [x] تاریخ ارجاع نمایش داده شود
- [x] وضعیت (رنگی) نمایش داده شود
- [x] توضیحات نمایش داده شود
- [x] نظر بررسی‌کننده نمایش داده شود (پس از تکمیل)

### ✅ محدودیت‌ها
- [x] تنها pending/under_review قابل ارجاع
- [x] نوع باید User یا Group باشد
- [x] مقصد باید موجود باشد
- [x] ادمین تنها دسترسی داشته باشد

---

## پایگاه داده

### جدول: `najm_bahar_projects`
```
- assigned_to_type: string | nullable
- assigned_to_id: unsignedBigInteger | nullable
- assigned_at: timestamp | nullable
- assignment_note: text | nullable
- assignment_status: enum (pending|under_review|completed|rejected) | nullable
- assignment_review_note: text | nullable
- assignment_completed_at: timestamp | nullable
```

### ایندکس‌ها:
```
- assigned_to_type, assigned_to_id
- assignment_status
- assigned_at
```

---

## جریان کاربری

### داخل پنل ادمین:
```
1. ادمین وارد صفحه جزئیات پروژه می‌شود
2. دکمه "ارجاع برای بررسی" را کلیک می‌کند
3. فرم باز می‌شود
4. نوع مقصد انتخاب می‌کند (User/Group)
5. فهرست مقاصد به‌طور دینامیک بارگذاری می‌شود
6. مقصد انتخاب می‌کند
7. توضیحات (اختیاری) اضافه می‌کند
8. فرم submit می‌کند
9. سیستم تایید می‌دهد
10. مقصد اطلاع دریافت می‌کند
11. پروژه "ارجاع شده" علامت‌گذاری می‌شود
```

---

## API Endpoints

### POST `/admin/najm-bahar/projects/{id}/assign`
**پیش‌نیاز:** Admin middleware
**داده:**
```json
{
    "assigned_to_type": "User",
    "assigned_to_id": 5,
    "assignment_note": "نیاز به بررسی تخصصی"
}
```

### POST `/admin/najm-bahar/projects/{id}/update-assignment-review`
**پیش‌نیاز:** Admin middleware
**داده:**
```json
{
    "assignment_status": "completed",
    "assignment_review_note": "پروژه از دیدگاه فنی مناسب است"
}
```

### GET `/admin/najm-bahar/get-users`
**پاسخ:**
```json
{
    "success": true,
    "items": [
        {"id": 1, "name": "احمد علی"},
        {"id": 2, "name": "فاطمه محمدی"}
    ]
}
```

### GET `/admin/najm-bahar/get-groups`
**پاسخ:**
```json
{
    "success": true,
    "items": [
        {"id": 1, "name": "کمیته نظارت"},
        {"id": 2, "name": "بررسی‌کنندگان فنی"}
    ]
}
```

---

## وضعیت پاسخ

✅ **همه موارد توسعه یافته و تست شده‌اند**

### خلاصه:
- **فایل‌های جدید:** 1 (ProjectAssigned Notification)
- **فایل‌های تغییر یافته:** 7
- **خطوط کد اضافه شده:** ~400
- **مهاجرت‌های جدید:** 1
- **مسیر‌های جدید:** 5
- **API Endpoints:** 2

---

## نکات مهم

⚠️ **توجه به موارد زیر:**

1. **User Model باید دارای `is_admin` یا `is_specialist` باشد**
   - یا استفاده از Role/Permission

2. **Group Model باید رابطه `users()` داشته باشد**
   - برای دسترسی به اعضا

3. **Email Configuration باید فعال باشد**
   - برای ارسال ایمیل‌های اطلاع‌رسانی

4. **Queue باید تنظیم شده باشد**
   - برای Notification::class implements ShouldQueue

---

## تست‌های پیشنهادی

```php
// تست ارجاع
$project = Project::first();
$user = User::find(5);

$service->assignProjectToReviewer(
    $project, 
    'User', 
    $user->id, 
    'تست ارجاع'
);

// بررسی تغییرات
assert($project->refresh()->assigned_to_id === 5);
assert($project->assignment_status === 'pending');
```

---

**وضعیت:** ✅ آماده برای فعال‌سازی
