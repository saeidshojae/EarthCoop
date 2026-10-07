# ممیزی حساب فرعی و انتقال داخلی نیتیو نجم بهار — ۷ اکتبر ۲۰۲۶

## نتیجه

بعد از تکمیل Native Membership Payment، External Active Transfer و Participation Activation، مهم‌ترین خلأ کاربردی نجم‌بهار در موبایل «حساب‌های فرعی و جابه‌جایی داخلی» است.

External transfer عمداً فقط از owned eligible subaccount انجام می‌شود؛ بنابراین اگر کاربر از داخل موبایل نتواند حساب فرعی بسازد یا Active را از حساب اصلی به آن منتقل کند، جریان انتقال بیرونی self-contained نیست.

هیچ deploy، انتقال واقعی یا تغییر production در این ممیزی انجام نشده است.

## زیرساخت موجود

- `SubAccountService::createSubAccount()` ساخت حساب فرعی را انجام می‌دهد و mirror canonical Account را نیز ایجاد می‌کند.
- `InternalAccountTransferService` canonical Main↔Sub را برای Active و Dim انجام می‌دهد.
- `InternalSubAccountTransferService` canonical Sub↔Sub همان مالک را برای Active و Dim انجام می‌دهد.
- انتقال‌های داخلی aggregate wealth را تغییر نمی‌دهند؛ فقط bucket/location داخلی پول را جابه‌جا می‌کنند.
- Active reservation در Main و subaccount در spend check لحاظ می‌شود.
- committed Dim در main local total حفظ می‌شود.
- سرویس‌ها double-entry transaction و domain idempotency دارند.
- legacy monetary methods در base `SubAccountService` بازنشسته شده‌اند و mutation باید از safe/canonical adapters عبور کند.
- وب فعلی ساخت/نام‌گذاری/انتقال/بستن حساب فرعی دارد، اما API v1 موبایل endpoint اختصاصی subaccount/internal-transfer ندارد.

## شکاف Native

1. API v1 فهرست کامل owned subaccounts را با local Active/Dim/available Active و status ارائه نمی‌کند.
2. API v1 ساخت حساب فرعی ندارد.
3. موبایل نمی‌تواند Main→Sub، Sub→Main یا Sub→Sub داخلی انجام دهد.
4. current external-transfer capability فقط sourceهای canonical existing را نشان می‌دهد؛ خودش نباید missing mirror بسازد.
5. برای mutation داخلی باید source/destination و bucket دقیق bind شوند؛ client نباید generic authority/meta بفرستد.
6. Active spendability باید reservation-aware باشد؛ Dim commitment نباید به‌عنوان spendable Dim ظاهر شود.
7. نتیجهٔ مبهم mutation مالی نیازمند same-intent retry و GET-only reconciliation است.
8. create/rename subaccount غیرمالی است، اما internal redistribution مالی است و باید از financial transport rules استفاده کند.
9. close/deactivate-with-transfer پیچیده‌تر است و در نسخهٔ اول Native وارد نمی‌شود.

## تصمیم

نسخهٔ اول Native Subaccount & Internal Redistribution شامل:
- GET فهرست owned active subaccounts + main balance projection؛
- POST ساخت subaccount با نام اختیاری محدود؛
- rename سادهٔ subaccount؛
- immediate internal transfer:
  - Main→Sub
  - Sub→Main
  - Sub→Sub همان owner
- bucket صریح Active یا Dim؛
- integer Gol؛
- optional description؛
- strict expected snapshot برای source spendability + exact destination identity؛
- idempotent POST، no automatic transport retry؛
- GET-only reconciliation؛
- no offline financial queue.

خارج از scope:
- close/deactivate account؛
- scheduled internal transfer؛
- group/legal-entity subaccounts؛
- cross-owner transfer (قبلاً جداگانه پیاده شده)؛
- automatic balancing؛
- production deployment/live movement.
