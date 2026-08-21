# ماتریس انطباق پیاده‌سازی انتخابات با سند E0

این سند وضعیت فنی پیاده‌سازی را در برابر `E0 — مشخصات حکمرانی، حقوق و سناریوهای پذیرش انتخابات پیوسته` ثبت می‌کند. E0 مرجع الزام‌آور است؛ وجود schema یا ذخیره داده به‌تنهایی به‌معنای تکمیل یک بند نیست. یک بند فقط زمانی «کامل» است که write path، read/access path، audit، policy و تست‌های لازم آن مطابق E0 بسته شده باشند.

## وضعیت کلی

| بخش E0 | وضعیت | توضیح |
|---|---|---|
| چرخه پیوسته، snapshot، توقف و اعمال نتیجه | کامل/تثبیت‌شده | lifecycle canonical، snapshot واجدان، snapshot رأی در توقف، continuity و cycle lineage دارای Gate مستقل هستند. |
| صلاحیت، رتبه‌بندی و تساوی | کامل/تثبیت‌شده | ranking جدا برای مدیر/بازرس، tally snapshot و قرعه قابل‌بازتولید با evidence ثبت شده‌اند. |
| نمایندگی پلکانی و انتصاب | عمدتاً کامل | appointment/representation، بالاترین کرسی معتبر، topology compression و succession پیاده شده‌اند؛ ماتریس عمومی تعارض مسئولیت‌ها باید جداگانه با متن نهایی E0 ممیزی شود. |
| قرارداد مسئولیت | عمدتاً کامل | قرارداد versioned/immutable، freeze per cycle، مهلت پاسخ policy-driven و offer audit شده‌اند؛ کامل‌بودن همه بندهای محتوایی قرارداد نیازمند ممیزی متن قراردادهای ادمین است. |
| 7.1 حریم رأی | جزئی | سه visibility canonical در write model وجود دارد و audit هویت را نگه می‌دارد؛ read/access layer مرکزی که ثابت کند هویت رأی محرمانه از تمام UI/reportهای عادی پنهان است هنوز باید تکمیل و Gate شود. |
| 7.2 دلیل تغییر/پس‌گرفتن رأی | جزئی | دلیل اختیاری، anonymous flag و سه دامنه visibility وجود دارد؛ اما reason هنوز به‌صورت جزء ballot event ذخیره می‌شود نه شیء مستقل کامل، moderation/human-review و privacy-safe read path و پاسخ موضوعی نامزد کامل نشده‌اند. |
| 7.3 محبوبیت و رضایت | باقیمانده | analytics امن، trend روزانه/هفتگی، net flow، retention، distance-to-cutoff، topic aggregation، suppression نمونه کوچک و smart trend notifications هنوز ساخته نشده‌اند. |
| عدالت رویه‌ای/درخواست بازبینی | باقیمانده/نیازمند فاز مستقل | audit زیرساختی وجود دارد، اما workflow رسمی درخواست بازبینی، مهلت‌ها، بازشماری و تصمیم مستند مطابق E0 هنوز به‌عنوان domain مستقل بسته نشده است. |
| سناریوهای پذیرش E0 | جزئی تا عمدتاً کامل | سناریوهای lifecycle/offer/timeout/replacement/crash recovery تا حد زیادی تست شده‌اند؛ سناریوهای وابسته به privacy/report/review تا تکمیل بخش‌های 7 و 8 باز می‌مانند. |
| تنظیمات نهایی E0 | جزئی | policy versioning/effective dating در E10 پیاده شده؛ تنظیمات privacy analytics مانند حداقل نمونه و بازه تجمیع هنوز اضافه نشده‌اند. |

## بخش 7 — حریم رأی و بازخورد

### 7.1 انتخاب افشا

وضعیت فعلی:

- `ElectionVoteVisibility` سه مقدار `confidential`, `all_members`, `elected_officials` دارد.
- Ballot v2 visibility را برای هر انتخاب ذخیره می‌کند؛ نبود انتخاب صریح در مسیر compatibility به `confidential` می‌افتد.
- `election_ballot_events` هویت رأی‌دهنده و رویداد را در audit append-only نگه می‌دارد.

باقی‌مانده الزامی:

- ایجاد یک read/access service واحد که خروجی رأی را براساس viewer، عضویت گروه، appointment فعال و visibility فیلتر کند.
- ممنوعیت قطعی نمایش `voter_id` رأی محرمانه در تمام UI/API/reportهای عادی، حتی برای مدیر/بازرس/نامزد.
- تست‌های negative authorization برای candidate، elected official، ordinary member و outsider.
- جداسازی مسیر audit حفاظت‌شده از مسیر نمایش عادی.

### 7.2 دلیل تغییر یا پس‌گرفتن رأی

وضعیت فعلی:

- دلیل اختیاری است.
- نام‌دار/ناشناس بودن (`comment_anonymous`) مستقل از دامنه نمایش است.
- visibility دلیل سه حالت `all_members`, `elected_officials`, `subject_only` دارد.
- cast/change/withdraw در audit trail ثبت می‌شوند.

باقی‌مانده الزامی:

- جداکردن reason/feedback به entity مستقل از vote event، با linkage کنترل‌شده و privacy-preserving.
- read policy مستقل برای reason، شامل `subject_only` فقط برای همان مدیر/بازرس هدف.
- جلوگیری از re-identification دلیل ناشناس از طریق timestamp، ordering، export یا joinهای گزارش.
- moderation pipeline پیش از نمایش برای تهدید، توهین، اطلاعات شخصی، نفرت‌پراکنی و spam.
- وضعیت `pending_review/visible/rejected/redacted` و مسیر human review برای موارد مشکوک.
- امکان پاسخ عمومی نامزد به «موضوع» بدون exposure هویت نویسنده ناشناس.

### 7.3 گزارش محبوبیت و رضایت

وضعیت فعلی:

- داده خام لازم برای بخشی از محاسبات از ballot events/tally موجود است، اما feature گزارش E0 هنوز ساخته نشده است.

باقی‌مانده الزامی:

- current vote count.
- daily/weekly trend.
- net inflow/outflow.
- distance to selection cutoff.
- vote retention rate.
- aggregate feedback topics.
- policy versioned برای `minimum_distinct_voters` با پیش‌فرض 10.
- policy versioned برای `minimum_aggregation_days` با پیش‌فرض 7.
- suppression خودکار breakdownها زیر حداقل نمونه/بازه و فقط نمایش آمار کلی.
- طراحی queryها به‌گونه‌ای که confidential voter identity از bucketهای کوچک، timestamp یا ترکیب فیلترها قابل استنتاج نباشد.
- smart notifications فقط برای meaningful trend و نه هر تغییر لحظه‌ای.

## ترتیب تکمیل پیشنهادی

1. بستن E10 و effective-dating/policy UI.
2. E11-Privacy: canonical visibility read layer + مستقل‌کردن feedback + moderation/review queue.
3. E12-Analytics: privacy-safe popularity/satisfaction reports + policy thresholds + meaningful-trend notifications.
4. E13-Review: procedural review/recount workflow مطابق E0.
5. اجرای یک E0 Acceptance Gate که همه سناریوهای E0 را end-to-end و fail-closed اجرا کند.

هیچ بند E0 صرفاً به‌خاطر وجود migration یا enum «کامل» اعلام نمی‌شود؛ وضعیت این ماتریس باید همراه هر فاز به‌روزرسانی شود.
