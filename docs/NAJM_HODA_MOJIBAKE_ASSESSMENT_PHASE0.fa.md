# ارزیابی اولیه mojibake در فاز 0

## تاریخ
- 2026-02-19

## دامنه بررسی
- `config/najm-hoda.php`
- `app/Services/NajmHoda/*`
- `app/Providers/NajmHodaServiceProvider.php`
- `app/Http/Controllers/API/NajmHodaController.php`
- `app/Http/Controllers/Admin/NajmHodaController.php`

## نتیجه اولیه
- بیشترین الگوهای مشکوک mojibake در این مرحله در فایل زیر دیده شد:
  - `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`

## تصمیم اجرایی
- در ادامه فاز 0، اصلاح mojibake به صورت هدفمند و مرحله ای انجام می شود:
1. `config/najm-hoda.php` و promptهای Agentها
2. `app/Services/NajmHoda/NajmHodaGroupAssistantService.php`
3. مرور مجدد خروجی UI/Log پس از هر مرحله

## نکته ایمنی
- اصلاحات encoding باید با backup و تست syntax انجام شود تا regression ایجاد نشود.
