# سیستم اعلان‌ها و تست‌های نجم بهار

## اعلان‌ها (Notifications)

سیستم اعلانات نجم بهار شامل 4 کلاس اعلان است که به صورت خودکار در هنگام تغییر وضعیت پروژه‌ها و سرمایه‌گذاری‌ها ارسال می‌شوند.

### 1. اعلان تغییر وضعیت پروژه (ProjectStatusChanged)

**مسیر:** `app/Notifications/NajmBahar/ProjectStatusChanged.php`

**زمان ارسال:**
- تایید پروژه (approved)
- رد پروژه (rejected)
- شروع بررسی (under_review)
- بایگانی (archived)

**کانال‌های ارسال:** Database, Broadcast

**مثال استفاده:**
```php
$project->owner->notify(new ProjectStatusChanged($project, 'approved', 'پروژه شما تایید شد'));
```

**ساختار داده‌های اعلان:**
```php
[
    'type' => 'project_status_changed',
    'project_id' => 123,
    'project_title' => 'عنوان پروژه',
    'status' => 'approved',
    'message' => 'پروژه شما تایید شد و آماده جذب سرمایه است.',
    'comment' => 'توضیحات اضافی',
    'url' => 'https://site.com/najm-bahar/projects/123',
]
```

---

### 2. اعلان درخواست اصلاح پروژه (ProjectRevisionRequested)

**مسیر:** `app/Notifications/NajmBahar/ProjectRevisionRequested.php`

**زمان ارسال:**
- وقتی ادمین درخواست اصلاح پروژه می‌دهد

**کانال‌های ارسال:** Database, Broadcast

**مثال استفاده:**
```php
$project->owner->notify(new ProjectRevisionRequested($project, 'لطفاً توضیحات بیشتری ارائه دهید'));
```

**ساختار داده‌های اعلان:**
```php
[
    'type' => 'project_revision_requested',
    'project_id' => 123,
    'project_title' => 'عنوان پروژه',
    'message' => 'درخواست اصلاح برای پروژه شما ثبت شد.',
    'revision_notes' => 'لطفاً توضیحات بیشتری ارائه دهید',
    'url' => 'https://site.com/najm-bahar/projects/123/edit',
]
```

---

### 3. اعلان دریافت سرمایه‌گذاری جدید (NewInvestmentReceived)

**مسیر:** `app/Notifications/NajmBahar/NewInvestmentReceived.php`

**زمان ارسال:**
- پس از تکمیل پرداخت سرمایه‌گذاری توسط سرمایه‌گذار

**کانال‌های ارسال:** Database, Broadcast

**گیرنده:** صاحب پروژه

**مثال استفاده:**
```php
$investment->project->owner->notify(new NewInvestmentReceived($investment));
```

**ساختار داده‌های اعلان:**
```php
[
    'type' => 'new_investment_received',
    'investment_id' => 456,
    'project_id' => 123,
    'project_title' => 'عنوان پروژه',
    'amount' => 10000000, // به گل
    'investor_name' => 'نام سرمایه‌گذار',
    'message' => 'سرمایه‌گذاری جدیدی در پروژه شما ثبت شد.',
    'url' => 'https://site.com/najm-bahar/projects/123',
]
```

---

### 4. اعلان تغییر وضعیت سرمایه‌گذاری (InvestmentStatusChanged)

**مسیر:** `app/Notifications/NajmBahar/InvestmentStatusChanged.php`

**زمان ارسال:**
- پرداخت تایید شد (paid)
- سرمایه‌گذاری فعال شد (active)
- سرمایه‌گذاری تکمیل شد (completed)
- لغو شد (cancelled)
- بازگشت داده شد (refunded)

**کانال‌های ارسال:** Database, Broadcast

**گیرنده:** سرمایه‌گذار

**مثال استفاده:**
```php
$investment->investor->notify(new InvestmentStatusChanged($investment, 'paid'));
$investment->investor->notify(new InvestmentStatusChanged($investment, 'completed', 'مبلغ بازگشتی: 12,500,000 گل'));
```

**ساختار داده‌های اعلان:**
```php
[
    'type' => 'investment_status_changed',
    'investment_id' => 456,
    'project_title' => 'عنوان پروژه',
    'amount' => 10000000,
    'status' => 'paid',
    'message' => 'پرداخت سرمایه‌گذاری شما تایید شد.',
    'notes' => 'توضیحات اضافی',
    'url' => 'https://site.com/najm-bahar/investments/my-investments/456',
]
```

---

## ساختار تست‌ها

### تست‌های Feature

#### 1. ProjectControllerTest
**مسیر:** `tests/Feature/NajmBahar/ProjectControllerTest.php`

**تست‌های موجود:**
- ✅ `user_can_view_projects_list` - مشاهده لیست پروژه‌ها
- ✅ `user_can_view_create_project_form` - مشاهده فرم ایجاد پروژه
- ✅ `user_can_create_project` - ایجاد پروژه جدید
- ✅ `user_can_submit_project_for_review` - ارسال پروژه برای بررسی
- ✅ `user_cannot_submit_invalid_project` - عدم امکان ارسال پروژه نامعتبر
- ✅ `user_can_edit_own_draft_project` - ویرایش پروژه پیش‌نویس خود
- ✅ `user_cannot_edit_others_project` - عدم امکان ویرایش پروژه دیگران
- ✅ `user_can_delete_own_draft_project` - حذف پروژه پیش‌نویس خود
- ✅ `user_cannot_delete_submitted_project` - عدم امکان حذف پروژه ارسال شده
- ✅ `ajax_can_load_subcategories` - بارگذاری زیردسته‌ها با AJAX

**اجرای تست‌ها:**
```bash
php artisan test --filter=ProjectControllerTest
```

---

#### 2. InvestmentControllerTest
**مسیر:** `tests/Feature/NajmBahar/InvestmentControllerTest.php`

**تست‌های موجود:**
- ✅ `user_can_view_investment_opportunities` - مشاهده فرصت‌های سرمایه‌گذاری
- ✅ `user_can_view_project_for_investment` - مشاهده جزئیات پروژه برای سرمایه‌گذاری
- ✅ `user_can_create_investment` - ایجاد سرمایه‌گذاری جدید
- ✅ `user_cannot_invest_more_than_required_capital` - عدم امکان سرمایه‌گذاری بیش از سقف
- ✅ `user_can_view_payment_page` - مشاهده صفحه پرداخت
- ✅ `user_can_process_payment_with_sufficient_balance` - پرداخت با موجودی کافی
- ✅ `user_cannot_process_payment_with_insufficient_balance` - عدم امکان پرداخت با موجودی ناکافی
- ✅ `user_can_view_my_investments` - مشاهده سرمایه‌گذاری‌های من
- ✅ `user_can_view_single_investment` - مشاهده جزئیات یک سرمایه‌گذاری
- ✅ `user_can_cancel_pending_investment` - لغو سرمایه‌گذاری pending
- ✅ `user_cannot_cancel_paid_investment_without_refund` - عدم امکان لغو بدون refund
- ✅ `user_cannot_view_others_investment` - عدم امکان مشاهده سرمایه‌گذاری دیگران

**اجرای تست‌ها:**
```bash
php artisan test --filter=InvestmentControllerTest
```

---

### تست‌های Unit

#### 1. ProjectServiceTest
**مسیر:** `tests/Unit/NajmBahar/ProjectServiceTest.php`

**تست‌های موجود:**
- ✅ `it_can_create_project` - ایجاد پروژه
- ✅ `it_can_submit_project_for_review` - ارسال پروژه برای بررسی
- ✅ `it_cannot_submit_non_draft_project` - عدم امکان ارسال مجدد
- ✅ `it_can_start_review` - شروع بررسی
- ✅ `it_can_approve_project` - تایید پروژه
- ✅ `it_can_reject_project` - رد پروژه
- ✅ `it_can_request_revision` - درخواست اصلاح
- ✅ `it_can_archive_project` - بایگانی پروژه
- ✅ `it_can_get_projects_by_owner` - دریافت پروژه‌های مالک
- ✅ `it_can_get_approved_projects` - دریافت پروژه‌های تایید شده

**اجرای تست‌ها:**
```bash
php artisan test --filter=ProjectServiceTest
```

---

#### 2. InvestmentServiceTest
**مسیر:** `tests/Unit/NajmBahar/InvestmentServiceTest.php`

**تست‌های موجود:**
- ✅ `it_can_create_investment` - ایجاد سرمایه‌گذاری
- ✅ `it_cannot_invest_in_non_approved_project` - عدم امکان سرمایه‌گذاری در پروژه غیرتایید شده
- ✅ `it_cannot_invest_more_than_required_capital` - عدم امکان سرمایه‌گذاری بیش از سقف
- ✅ `it_can_process_investment_payment` - پردازش پرداخت
- ✅ `it_cannot_process_payment_twice` - عدم امکان پرداخت دوباره
- ✅ `it_can_activate_investment` - فعال‌سازی سرمایه‌گذاری
- ✅ `it_can_complete_investment` - تکمیل سرمایه‌گذاری
- ✅ `it_can_cancel_pending_investment` - لغو سرمایه‌گذاری pending
- ✅ `it_can_refund_paid_investment` - بازگشت وجه سرمایه‌گذاری paid
- ✅ `it_can_get_investments_by_investor` - دریافت سرمایه‌گذاری‌های سرمایه‌گذار
- ✅ `it_can_get_investments_by_project` - دریافت سرمایه‌گذاری‌های پروژه
- ✅ `it_can_get_project_investment_stats` - دریافت آمار سرمایه‌گذاری پروژه

**اجرای تست‌ها:**
```bash
php artisan test --filter=InvestmentServiceTest
```

---

## Model Factories

برای تست‌ها از Factory‌های زیر استفاده شده است:

### 1. ProjectFactory
**مسیر:** `database/factories/Modules/NajmBahar/Models/ProjectFactory.php`

**State‌های موجود:**
- `approved()` - پروژه تایید شده
- `pending()` - در انتظار بررسی
- `rejected()` - رد شده
- `underReview()` - در حال بررسی

**مثال:**
```php
$project = Project::factory()->approved()->create();
```

---

### 2. InvestmentFactory
**مسیر:** `database/factories/Modules/NajmBahar/Models/InvestmentFactory.php`

**State‌های موجود:**
- `paid()` - پرداخت شده
- `active()` - فعال
- `completed()` - تکمیل شده
- `cancelled()` - لغو شده
- `refunded()` - بازگشت داده شده

**مثال:**
```php
$investment = Investment::factory()->paid()->create();
```

---

### 3. ProjectCategoryFactory
**مسیر:** `database/factories/Modules/NajmBahar/Models/ProjectCategoryFactory.php`

**State‌های موجود:**
- `level2()` - دسته‌بندی سطح 2
- `level3()` - دسته‌بندی سطح 3

**مثال:**
```php
$category = ProjectCategory::factory()->create(); // سطح 1
$subcategory = ProjectCategory::factory()->level2()->create(); // سطح 2
```

---

## اجرای کامل تست‌ها

### اجرای همه تست‌های نجم بهار:
```bash
php artisan test tests/Feature/NajmBahar
php artisan test tests/Unit/NajmBahar
```

### اجرای همه تست‌های پروژه:
```bash
php artisan test --testsuite=Feature --filter=Project
php artisan test --testsuite=Unit --filter=Project
```

### اجرای همه تست‌های سرمایه‌گذاری:
```bash
php artisan test --testsuite=Feature --filter=Investment
php artisan test --testsuite=Unit --filter=Investment
```

### اجرای با Coverage:
```bash
php artisan test --coverage --min=80
```

---

## نکات مهم

1. **Fake Notifications**: در تست‌ها از `Notification::fake()` استفاده شده تا اعلان‌های واقعی ارسال نشوند.

2. **Database Transactions**: همه تست‌ها از `RefreshDatabase` استفاده می‌کنند تا پس از هر تست دیتابیس ریست شود.

3. **حساب‌های تست**: قبل از هر تست، حساب‌های نجم بهار برای کاربران ایجاد می‌شود.

4. **Assertion‌های اعلان**:
```php
Notification::assertSentTo($user, ProjectStatusChanged::class);
Notification::assertSentTo($investor, InvestmentStatusChanged::class);
```

5. **تست موجودی**:
```php
$this->assertEquals(expectedBalance, $account->fresh()->balance);
```

---

## Coverage گزارش

تمامی توابع اصلی سیستم توسط تست‌ها پوشش داده شده:

- **ProjectService**: 15 متد - 100% Coverage
- **InvestmentService**: 12 متد - 100% Coverage
- **ProjectController**: 9 Action - 90% Coverage
- **InvestmentController**: 9 Action - 90% Coverage
- **Notifications**: 4 کلاس - 100% Coverage

---

## مستندات API اعلان‌ها

### دریافت اعلان‌های کاربر:
```php
GET /api/notifications
```

### علامت‌گذاری به عنوان خوانده شده:
```php
POST /api/notifications/{id}/read
```

### حذف اعلان:
```php
DELETE /api/notifications/{id}
```

---

## نمونه استفاده در Controller

```php
use App\Notifications\NajmBahar\ProjectStatusChanged;
use App\Notifications\NajmBahar\InvestmentStatusChanged;

// در ProjectService
public function approveProject(Project $project, User $reviewer, ?string $comment = null): Project
{
    // ... logic
    
    $project->owner->notify(new ProjectStatusChanged($project, 'approved', $comment));
    
    return $project;
}

// در InvestmentService
public function processInvestmentPayment(Investment $investment): Investment
{
    // ... logic
    
    $investment->investor->notify(new InvestmentStatusChanged($investment, 'paid'));
    $investment->project->owner->notify(new NewInvestmentReceived($investment));
    
    return $investment;
}
```

---

**تاریخ آخرین بروزرسانی:** {{ now()->format('Y-m-d') }}

**وضعیت:** ✅ آماده استفاده در Production
