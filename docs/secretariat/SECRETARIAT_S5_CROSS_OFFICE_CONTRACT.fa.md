# قرارداد Cross-Office در Phase S5 دبیرخانه EarthCoop

## هدف

S5 اولین فازی است که یک Case می‌تواند رکورد رسمی دفتر دیگری را در یک پرونده موضوعی کنار اسناد دفتر مقصد نشان دهد، بدون اینکه Source of Truth سند جابه‌جا یا کپی بی‌قاعده شود.

اصل حاکم:

> **Cross-office aggregation is a reference, not ownership transfer.**

## 1. Reference — فعال در S5

عملیات `reference` تنها عملیات بین‌دفتری فعال در S5 است.

قواعد:

1. رکورد مبدأ باید رسمی و دارای `registry_number` باشد.
2. Case مقصد نباید archived باشد.
3. actor باید مجاز به `manage` کردن Case مقصد باشد.
4. actor باید Office مبدأ را بتواند ببیند.
5. actor باید خود Record مبدأ را طبق `SecretariatRecordPolicy` بتواند ببیند.
6. `record.office_id` تغییر نمی‌کند.
7. Version، attachment، registry number و provenance رکورد کپی نمی‌شوند.
8. pivot پرونده فقط `cross_office_reference` و `source_office_id` را ثبت می‌کند.
9. هر viewer پرونده هنگام نمایش، دوباره از Policy همان Record مبدأ عبور می‌کند؛ visibility پرونده هرگز confidentiality رکورد عضو را کاهش نمی‌دهد.
10. audit در دو سمت ثبت می‌شود:
   - Office مقصد: ایجاد reference به رکورد خارجی، بدون نسبت‌دادن record_id خارجی به ownership مقصد.
   - Office مبدأ: اینکه رکورد رسمی آن توسط Case دفتر دیگری reference شده است.

## 2. Transfer — در S5 به معنی move نیست

انتقال سازمانی یک سند رسمی **نباید** با update کردن `record.office_id` انجام شود.

رکورد ثبت‌شده هویت ثبتی و شماره خود را در Office مبدأ حفظ می‌کند.

اگر workflow آینده نیازمند «ارجاع برای اقدام» باشد، آن concern باید با Dispatch/Referral و reference بین‌دفتری مدل شود.

اگر دفتر مقصد در آینده لازم باشد سند رسمی مستقل خود را ثبت کند، آن یک Record جدید با provenance/relation صریح خواهد بود؛ نه انتقال Aggregate موجود.

بنابراین در S5:

- `move registered record between offices` = ممنوع
- `reference foreign record` = مجاز با Policy
- `dispatch/referral for action` = مسیر مناسب گردش سازمانی

## 3. Copy — غیرفعال تا قرارداد snapshot رسمی

S5 عملیات عمومی `copy` ارائه نمی‌دهد.

در صورت نیاز آینده، copy فقط می‌تواند:

- Record جدید در Office مقصد بسازد؛
- source/provenance مبدأ را نگه دارد؛
- relation صریح مانند `derived_from/refers_to` داشته باشد؛
- snapshot/version/checksum مستقل داشته باشد؛
- actor و دلیل ایجاد copy را audit کند.

کپی خام row، attachment یا registry identity ممنوع است.

## 4. Case semantics

Case مالک business truth رکورد نیست.

Case فقط این facts را مالک است:

- چه Recordای به پرونده مرتبط شده؛
- رابطه local یا cross-office است؛
- source Office کدام است؛
- role سند در پرونده چیست؛
- چه actor و چه زمانی آن reference را افزوده است.

تمام محتوای سند، status رسمی، version، ACL و provenance همچنان متعلق به `SecretariatRecord` و Source Domain آن است.

## 5. Security invariant

وجود یک Record در Case هرگز مجوز دیدن آن Record ایجاد نمی‌کند.

مثال:

- عضو دفتر A می‌تواند Case A را ببیند.
- Case A به Record دفتر B اشاره می‌کند.
- اگر عضو A در دفتر B/ACL آن Record مجوز ندارد، حتی title و registry number آن Record در Case A نمایش داده نمی‌شود.

این invariant برای deterministic search و semantic retrieval آینده نیز الزامی است.

## 6. Current implementation boundary

S5 پیاده می‌کند:

- local case membership
- cross-office reference
- source-office provenance روی pivot
- permission-aware rendering
- dual-sided audit

S5 پیاده نمی‌کند:

- move رسمی Record
- generic copy
- cross-office approval delegation
- authority جدید برای legal_entity/committee بدون Source Domain واقعی
- bypass کردن ACL از طریق Case
