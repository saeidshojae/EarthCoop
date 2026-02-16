# مستندات ماژول پروژه‌ها و سرمایه‌گذاری - نجم بهار

## خلاصه تغییرات

این ماژول امکان ثبت پروژه‌های سرمایه‌گذاری توسط کاربران و گروه‌ها، بررسی و تایید توسط ادمین، و سرمایه‌گذاری توسط سایر کاربران را فراهم می‌کند.

---

## فایل‌های ایجاد شده

### 1. Migrations (دیتابیس)

#### `database/migrations/2026_02_14_100000_create_najm_bahar_project_categories_table.php`
- جدول دسته‌بندی سه‌سطحی پروژه‌ها
- ساختار سلسله‌مراتبی با `parent_id`
- فیلدها: `name`, `parent_id`, `level` (1-3), `order`, `status`

#### `database/migrations/2026_02_14_100001_create_najm_bahar_projects_table.php`
- جدول اصلی پروژه‌ها
- روابط polymorphic برای `owner` (User یا Group)
- دسته‌بندی سه‌سطحی: `category_level1_id`, `category_level2_id`, `category_level3_id`
- فیلدهای کلیدی:
  - `title`: عنوان پروژه
  - `project_type`: نوع (public/private)
  - `summary`: خلاصه
  - `description`: توضیحات کامل
  - `required_capital`: سرمایه مورد نیاز (به GOL)
  - `profit_percentage`: درصد سود
  - `investment_duration_months`: مدت سرمایه‌گذاری
  - `attachments`: فایل‌های پیوست (JSON)
  - `status`: وضعیت (draft, pending, under_review, approved, rejected, archived)
  - `rejection_reason`: دلیل رد
  - تاریخ‌های مهم: `submitted_at`, `reviewed_at`, `approved_at`, `archived_at`

#### `database/migrations/2026_02_14_100002_create_najm_bahar_project_reviews_table.php`
- تاریخچه بررسی و تغییرات وضعیت پروژه‌ها
- ثبت هر اقدام ادمین با جزئیات
- فیلدها: `project_id`, `reviewer_id`, `action`, `comment`, `metadata`

#### `database/migrations/2026_02_14_100003_create_najm_bahar_investments_table.php`
- جدول سرمایه‌گذاری‌ها
- روابط polymorphic برای `investor` (User یا Group)
- لینک به `transaction_id` از جدول تراکنش‌های نجم بهار
- فیلدهای کلیدی:
  - `project_id`: پروژه مورد سرمایه‌گذاری
  - `amount`: مبلغ سرمایه‌گذاری (GOL)
  - `agreed_profit_percentage`: درصد سود توافق شده
  - `expected_return`: بازگشت پیش‌بینی شده
  - `transaction_id`: شناسه تراکنش پرداخت
  - `status`: وضعیت (pending, paid, active, completed, cancelled, refunded)
  - `invested_at`, `maturity_date`, `completed_at`

---

### 2. Models

#### `app/Modules/NajmBahar/Models/ProjectCategory.php`
- مدل دسته‌بندی با روابط سلسله‌مراتبی
- متدها:
  - `parent()`: دسته‌بندی والد
  - `children()`: دسته‌بندی‌های فرزند
  - `projectsLevel1/2/3()`: پروژه‌های مربوط به هر سطح
  - `scopeActive()`, `scopeLevel()`, `scopeRoot()`
  - `getFullPathAttribute()`: مسیر کامل دسته‌بندی

#### `app/Modules/NajmBahar/Models/Project.php`
- مدل اصلی پروژه
- روابط:
  - `owner()`: polymorphic به User یا Group
  - `categoryLevel1/2/3()`: دسته‌بندی‌های سه‌گانه
  - `reviews()`: تاریخچه بررسی‌ها
  - `investments()`: سرمایه‌گذاری‌های این پروژه
- اسکوپ‌ها: `approved()`, `pending()`, `rejected()`, `public()`, `private()`
- Attributes محاسباتی:
  - `total_invested`: مجموع سرمایه جمع‌آوری شده
  - `investment_progress`: درصد پیشرفت
  - `investors_count`: تعداد سرمایه‌گذاران
  - `category_path`: مسیر کامل دسته‌بندی
- متدها:
  - `isInvestable()`: آیا قابل سرمایه‌گذاری است؟

#### `app/Modules/NajmBahar/Models/ProjectReview.php`
- مدل تاریخچه بررسی‌ها
- روابط: `project()`, `reviewer()`
- Attributes: `action_label`, `action_color`

#### `app/Modules/NajmBahar/Models/Investment.php`
- مدل سرمایه‌گذاری
- روابط:
  - `project()`: پروژه
  - `investor()`: polymorphic به User یا Group
  - `transaction()`: تراکنش پرداخت
- اسکوپ‌ها: `active()`, `completed()`, `paid()`
- Attributes: `status_label`, `status_color`
- متد: `calculateExpectedReturn()`

#### بروزرسانی `app/Models/User.php` و `app/Models/Group.php`
- اضافه شدن روابط polymorphic:
  - `najmBaharProjects()`: پروژه‌های ثبت شده
  - `najmBaharInvestments()`: سرمایه‌گذاری‌های انجام شده

---

### 3. Services

#### `app/Modules/NajmBahar/Services/ProjectService.php`
سرویس مدیریت پروژه‌ها با متدهای زیر:

**ایجاد و ویرایش:**
- `createProject($owner, $data)`: ایجاد پروژه جدید
- `updateProject($project, $data)`: ویرایش پروژه
- `submitForReview($project)`: ارسال برای بررسی

**مدیریت توسط ادمین:**
- `startReview($project, $reviewer)`: شروع بررسی
- `approveProject($project, $reviewer, $comment)`: تایید
- `rejectProject($project, $reviewer, $reason, $comment)`: رد
- `requestRevision($project, $reviewer, $notes)`: درخواست اصلاح
- `archiveProject($project, $admin, $reason)`: بایگانی

**دریافت داده:**
- `getProjectsByOwner($owner, $statuses)`: پروژه‌های یک مالک
- `getPendingProjects()`: پروژه‌های در انتظار بررسی
- `getApprovedProjects($filters)`: پروژه‌های تایید شده
- `getProjectStatistics($owner)`: آمار پروژه‌ها

**اعتبارسنجی داخلی:**
- `validateCategoryHierarchy()`: بررسی صحت سلسله‌مراتب دسته‌بندی
- `validateProjectData()`: اعتبارسنجی اطلاعات پروژه
- `createReview()`: ثبت تاریخچه بررسی

#### `app/Modules/NajmBahar/Services/InvestmentService.php`
سرویس مدیریت سرمایه‌گذاری‌ها با متدهای زیر:

**ایجاد و مدیریت:**
- `createInvestment($project, $investor, $amount, $options)`: ثبت درخواست سرمایه‌گذاری
- `processInvestmentPayment($investment, $payer, $trackingCode)`: پردازش پرداخت و انتقال وجه
- `activateInvestment($investment)`: فعال‌سازی
- `completeInvestment($investment, $actualReturn, $notes)`: تکمیل و بازگشت سرمایه
- `cancelInvestment($investment, $reason)`: لغو و بازپرداخت

**دریافت داده:**
- `getInvestmentsByInvestor($investor, $statuses)`: سرمایه‌گذاری‌های یک سرمایه‌گذار
- `getInvestmentsByProject($project, $statuses)`: سرمایه‌گذاری‌های یک پروژه
- `getProjectInvestmentStats($project)`: آمار سرمایه‌گذاری پروژه
- `getInvestorStats($investor)`: آمار سرمایه‌گذار

**یکپارچگی با TransactionService:**
- استفاده از `TransactionService::transfer()` برای انتقال وجه
- لینک به `AccountService` برای دریافت حساب‌های کاربران و گروه‌ها

---

### 4. Controllers

#### `app/Http/Controllers/NajmBahar/ProjectController.php`
کنترلر پروژه‌ها برای کاربران:
- `index()`: لیست پروژه‌های کاربر
- `create()`: فرم ایجاد
- `store()`: ذخیره پروژه جدید
- `show()`: نمایش جزئیات
- `edit()`: فرم ویرایش
- `update()`: بروزرسانی
- `submit()`: ارسال برای بررسی
- `destroy()`: حذف (فقط draft)
- `getSubCategories()`: AJAX endpoint برای دریافت زیردسته‌ها

#### `app/Http/Controllers/Admin/NajmBahar/ProjectController.php`
کنترلر مدیریت پروژه‌ها برای ادمین:
- `index()`: لیست پروژه‌ها با فیلتر وضعیت
- `show()`: نمایش جزئیات برای بررسی
- `startReview()`: شروع بررسی
- `approve()`: تایید
- `reject()`: رد
- `requestRevision()`: درخواست اصلاح
- `archive()`: بایگانی

#### `app/Http/Controllers/NajmBahar/InvestmentController.php`
کنترلر سرمایه‌گذاری:
- `index()`: لیست پروژه‌های قابل سرمایه‌گذاری
- `show()`: نمایش جزئیات پروژه
- `store()`: ثبت درخواست سرمایه‌گذاری
- `payment()`: صفحه پرداخت
- `processPayment()`: پردازش پرداخت
- `myInvestments()`: سرمایه‌گذاری‌های کاربر
- `showInvestment()`: جزئیات یک سرمایه‌گذاری
- `cancel()`: لغو سرمایه‌گذاری

---

### 5. Policies

#### `app/Policies/NajmBahar/ProjectPolicy.php`
- `view()`: صاحب یا پروژه‌های عمومی تایید شده
- `update()`: فقط صاحب و فقط draft یا rejected
- `delete()`: فقط صاحب و فقط draft

#### `app/Policies/NajmBahar/InvestmentPolicy.php`
- `view()`: سرمایه‌گذار یا صاحب پروژه
- `update()`: فقط سرمایه‌گذار
- `delete()`: فقط سرمایه‌گذار و فقط pending یا paid

---

### 6. Routes

#### `routes/najm-bahar.php`
**مسیرهای پروژه:**
```
GET    /najm-bahar/projects                      → لیست پروژه‌های من
GET    /najm-bahar/projects/create               → فرم ایجاد
POST   /najm-bahar/projects                      → ذخیره
GET    /najm-bahar/projects/{project}            → نمایش جزئیات
GET    /najm-bahar/projects/{project}/edit       → فرم ویرایش
PUT    /najm-bahar/projects/{project}            → بروزرسانی
DELETE /najm-bahar/projects/{project}            → حذف
POST   /najm-bahar/projects/{project}/submit     → ارسال برای بررسی
GET    /najm-bahar/projects/categories/sub-categories → AJAX
```

**مسیرهای سرمایه‌گذاری:**
```
GET    /najm-bahar/investments                   → لیست فرصت‌ها
GET    /najm-bahar/investments/projects/{project} → جزئیات پروژه
POST   /najm-bahar/investments/projects/{project} → ثبت سرمایه‌گذاری
GET    /najm-bahar/investments/my-investments    → سرمایه‌گذاری‌های من
GET    /najm-bahar/investments/my-investments/{investment} → جزئیات
GET    /najm-bahar/investments/{investment}/payment → صفحه پرداخت
POST   /najm-bahar/investments/{investment}/payment → پردازش
POST   /najm-bahar/investments/{investment}/cancel  → لغو
```

**مسیرهای ادمین:**
```
GET    /admin/najm-bahar/projects                → لیست (با فیلتر)
GET    /admin/najm-bahar/projects/{project}      → نمایش جزئیات
POST   /admin/najm-bahar/projects/{project}/start-review → شروع بررسی
POST   /admin/najm-bahar/projects/{project}/approve → تایید
POST   /admin/najm-bahar/projects/{project}/reject  → رد
POST   /admin/najm-bahar/projects/{project}/request-revision → اصلاح
POST   /admin/najm-bahar/projects/{project}/archive → بایگانی
```

---

### 7. Views

#### کاربران:
- `resources/views/najm-bahar/projects/index.blade.php`: لیست پروژه‌های من
- `resources/views/najm-bahar/investments/index.blade.php`: لیست فرصت‌های سرمایه‌گذاری

#### ادمین:
- `resources/views/admin/najm-bahar/projects/index.blade.php`: مدیریت پروژه‌ها

#### سایدبار:
- `resources/views/najm-bahar/partials/sidebar.blade.php`: بروزرسانی شده با منوهای:
  - سرمایه‌گذاری → فرصت‌ها، سرمایه‌گذاری‌های من
  - پروژه‌ها → پروژه‌های من، ایجاد جدید

---

## فلوی کاری (Workflow)

### 1. ثبت پروژه توسط کاربر/گروه
1. کاربر وارد "پروژه‌ها → ایجاد پروژه جدید" می‌شود
2. فرم پر می‌شود (عنوان، دسته‌بندی ۳ سطحی، نوع، خلاصه، توضیحات، سرمایه، درصد سود، مدت، فایل‌های پیوست)
3. Submit → پروژه با وضعیت `draft` ذخیره می‌شود
4. کاربر می‌تواند ویرایش کند یا برای بررسی ارسال کند
5. ارسال → وضعیت به `pending` تغییر می‌کند

### 2. بررسی توسط ادمین
1. ادمین وارد پنل مدیریت پروژه‌ها می‌شود
2. پروژه‌های `pending` را مشاهده می‌کند
3. روی "بررسی" کلیک → جزئیات کامل نمایش داده می‌شود
4. ادمین می‌تواند:
   - **تایید کند** → وضعیت به `approved` (قابل سرمایه‌گذاری می‌شود)
   - **رد کند** → وضعیت به `rejected` + دلیل رد
   - **اصلاح درخواست کند** → وضعیت به `rejected` + توضیحات اصلاحی
   - **بایگانی کند** → وضعیت به `archived`

### 3. اصلاح و ارسال مجدد
1. اگر پروژه رد شد، کاربر می‌تواند ویرایش کند
2. پس از اصلاح، دوباره Submit می‌کند
3. تاریخچه در جدول `project_reviews` ثبت می‌شود

### 4. سرمایه‌گذاری
1. کاربران وارد "سرمایه‌گذاری → فرصت‌ها" می‌شوند
2. فقط پروژه‌های `approved` نمایش داده می‌شوند
3. انتخاب پروژه → مشاهده جزئیات کامل + پیشرفت سرمایه‌گذاری
4. ثبت درخواست سرمایه‌گذاری → رکورد با وضعیت `pending` ایجاد می‌شود
5. هدایت به صفحه پرداخت
6. پرداخت → استفاده از `InvestmentService::processInvestmentPayment()`:
   - دریافت حساب سرمایه‌گذار و صاحب پروژه
   - فراخوانی `TransactionService::transfer()`
   - ثبت تراکنش در جدول `najm_transactions`
   - لینک `transaction_id` به رکورد investment
   - تغییر وضعیت به `paid` و سپس `active`
7. پیگیری در "سرمایه‌گذاری‌های من"

### 5. تکمیل و بازگشت سرمایه
1. صاحب پروژه یا ادمین `InvestmentService::completeInvestment()` را فراخوانی می‌کند
2. مبلغ اصلی + سود از حساب صاحب پروژه به سرمایه‌گذار برگردانده می‌شود
3. وضعیت به `completed` تغییر می‌کند

---

## نکات فنی مهم

### Polymorphic Relationships
- هم `User` و هم `Group` می‌توانند صاحب پروژه یا سرمایه‌گذار باشند
- استفاده از `morphTo()` و `morphMany()`

### یکپارچگی با سیستم تراکنش‌های نجم بهار
- همه پرداخت‌ها از طریق `TransactionService` انجام می‌شود
- لینک مستقیم به جدول `najm_transactions`
- تطابق کامل با سیستم GOL/Bahar

### دسته‌بندی سه‌سطحی
- مثال: "کشاورزی (سطح ۱) → باغبانی (سطح ۲) → پرورش گل (سطح ۳)"
- اعتبارسنجی سلسله‌مراتب در `ProjectService`

### تاریخچه کامل
- هر تغییر وضعیت در `project_reviews` ثبت می‌شود
- شامل: action، reviewer، comment، metadata

### Soft Deletes
- پروژه‌ها و سرمایه‌گذاری‌ها از soft delete استفاده می‌کنند
- قابلیت بازیابی در صورت نیاز

---

## گام‌های بعدی (TODO)

### Views باقی‌مانده:
- [ ] `projects/create.blade.php`: فرم ایجاد پروژه
- [ ] `projects/edit.blade.php`: فرم ویرایش
- [ ] `projects/show.blade.php`: نمایش جزئیات پروژه
- [ ] `investments/show.blade.php`: نمایش جزئیات پروژه برای سرمایه‌گذاری
- [ ] `investments/payment.blade.php`: صفحه پرداخت
- [ ] `investments/my-investments.blade.php`: لیست سرمایه‌گذاری‌های من
- [ ] `investments/show-investment.blade.php`: جزئیات یک سرمایه‌گذاری
- [ ] `admin/najm-bahar/projects/show.blade.php`: نمایش جزئیات برای ادمین

### ویژگی‌های اضافی:
- [ ] سیستم اعلان‌ها (تایید، رد، سرمایه‌گذاری جدید)
- [ ] آپلود و مدیریت فایل‌های پیوست
- [ ] گزارش‌گیری از سرمایه‌گذاری‌ها
- [ ] داشبورد آماری برای ادمین
- [ ] پرداخت خودکار سود در سررسید
- [ ] سیستم امتیازدهی به پروژه‌ها
- [ ] نظرات و پرسش و پاسخ در پروژه‌ها

### تست:
- [ ] Unit tests برای Services
- [ ] Feature tests برای Controllers
- [ ] Integration tests برای فلوی کامل
- [ ] تست Policy ها

### بهینه‌سازی:
- [ ] Eager loading برای جلوگیری از N+1 queries
- [ ] Cache برای لیست دسته‌بندی‌ها
- [ ] Queue برای ارسال اعلان‌ها
- [ ] API endpoints برای موبایل اپلیکیشن

---

## دستور اجرای Migration

```bash
php artisan migrate
```

این دستور ۴ جدول زیر را ایجاد می‌کند:
- `najm_bahar_project_categories`
- `najm_bahar_projects`
- `najm_bahar_project_reviews`
- `najm_bahar_investments`

---

## نمونه استفاده از Services

### ایجاد پروژه:
```php
use App\Modules\NajmBahar\Services\ProjectService;

$projectService = app(ProjectService::class);

$project = $projectService->createProject(auth()->user(), [
    'title' => 'پرورش گل رز ارگانیک',
    'category_level1_id' => 1, // کشاورزی
    'category_level2_id' => 5, // باغبانی
    'category_level3_id' => 12, // پرورش گل
    'project_type' => 'public',
    'summary' => 'پرورش گل رز ارگانیک برای صادرات',
    'required_capital' => 5000000, // 5 میلیون گل
    'profit_percentage' => 15.5,
    'investment_duration_months' => 12,
]);

// ارسال برای بررسی
$projectService->submitForReview($project);
```

### سرمایه‌گذاری:
```php
use App\Modules\NajmBahar\Services\InvestmentService;

$investmentService = app(InvestmentService::class);

// ثبت درخواست
$investment = $investmentService->createInvestment(
    $project,
    auth()->user(),
    500000 // 500 هزار گل
);

// پرداخت
$investmentService->processInvestmentPayment(
    $investment,
    auth()->user()
);
```

---

تاریخ ایجاد: {{ now()->format('Y-m-d H:i:s') }}
