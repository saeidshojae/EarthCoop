# ماتریس قابلیت‌های مدیریتی نجم هدا در گروه‌ها

## هدف
این سند مرجع ممیزی قابلیت‌های کنسول «خدمات مدیریتی» نجم هدا برای مدیران و بازرسان گروه است. هدف جلوگیری از دوباره‌کاری، روشن‌بودن حدود مسئولیت نجم هدا، و مشخص‌کردن قابلیت‌های پوشش‌داده‌شده، ناقص، خارج از دامنه یا نیازمند ماژول مستقل است.

## قواعد معماری ثابت
- کاربر در کل EarthCoop یک کاربر عادی است؛ قابلیت مدیریتی فقط در context گروهی که در آن مدیر یا بازرس فعال است ظاهر می‌شود.
- UI کنسول جایگزین policy و authorization backend نیست.
- عملیات تغییردهنده state باید از preview/confirmation عبور کنند؛ عملیات read-only می‌توانند مستقیم اجرا شوند.
- دکمه‌های کنسول تا جای ممکن wrapper بر workflowهای موجود هستند؛ از ساخت backend موازی پرهیز می‌شود.
- انتخابات سیستمی انتخاب مدیر/بازرس عمداً از این کنسول خارج است.

## وضعیت قابلیت‌ها

| حوزه | قابلیت | مدیر | بازرس | وضعیت | مسیر/منبع | یادداشت |
|---|---|---:|---:|---|---|---|
| نشست | تنظیم نشست رسمی | ✓ | ✓ | Covered | Najm Hoda meeting workflow | guided form + preview/confirm |
| نشست | مشاهده وضعیت نشست‌ها | ✓ | ✓ | Covered | management snapshot | badge زنده |
| نشست | پایان نشست | ✓ | ✓ | Covered | Najm Hoda meeting workflow | مجوزهای موقت مشارکت پاک می‌شوند |
| نشست | مدیریت مشارکت/دست بلندکردن | ✓ | ✓ | Covered | SessionParticipation | modal native + badge |
| صورتجلسه | تولید پیش‌نویس | ✓ | ✓ | Covered | meeting minute service | evidence-based |
| صورتجلسه | مشاهده سند مدیریتی | ✓ | ✓ | Covered | management document renderer | snapshot رسمی + وضعیت اجرایی زنده |
| صورتجلسه | تأیید صورتجلسه | ✓ | ✓ | Covered | Najm Hoda workflow | انسانی و قابل ممیزی |
| تصمیمات | استخراج تصمیمات | ✓ | ✓ | Covered | decision candidate service | evidence grounded |
| تصمیمات | تأیید تصمیمات | ✓ | ✓ | Covered | decision workflow | confirmed/candidate تفکیک شده |
| اقدامات | استخراج Action Item | ✓ | ✓ | Covered | action candidate service | evidence grounded |
| اقدامات | صف اقدام | ✓ | ✓ | Covered | action item service | open/in_progress/blocked/done |
| اقدامات | ویرایش وضعیت/مسئول/موعد/اولویت | ✓ | ✓ | Covered | guided action editor | history حفظ می‌شود |
| اقدامات | توجه proactive | ✓ | ✓ | Covered | attention evaluator/delivery/sweep | scheduler متصل است |
| محتوا | ساخت پست | ✓ | ✓ | Covered | Najm Hoda executor | guided + preview/confirm |
| محتوا | ساخت نظرسنجی | ✓ | ✓ | Covered | Najm Hoda executor | guided + preview/confirm |
| محتوا | ثبت نظر/کامنت | ✓ | ✓ | Covered | Najm Hoda executor | هدف مشخص می‌شود |
| محتوا | ثبت واکنش | ✓ | ✓ | Covered | Najm Hoda executor | message/post/comment |
| محتوا | ارسال پیام گروه | ✓ | ✓ | Covered | native composer | کنسول به composer اصلی می‌برد |
| حکمرانی | ایجاد انتخابات درون‌گروهی | ✓ | ✓ | Covered | GroupChat.elections.openAdmin | مستقل از انتخابات سیستمی |
| حکمرانی | مدیریت انتخابات درون‌گروهی | ✓ | ✓ | Covered | group info election tab | native workflow |
| گروه | ویرایش گروه | ✓ | ✓ | Covered | GroupChatPageChrome | wrapper native |
| گروه | افزودن مهمان | ✓ | ✓ | Covered | native group UI | wrapper native |
| گروه | درخواست چت مدیران | ✓ | ✓ | Covered | native group UI | صف pending برای مدیر badge دارد |
| گروه | مدیریت اعضا | ✓ | ✓* | Covered | native modal | مجوز نهایی backend/UI تعیین‌کننده است |
| گروه | تنظیمات گروه | ✓ | ✓ | Covered | native modal | wrapper native |
| نظارت | گزارش‌ها و رسیدگی | ✓ | — | Covered | native reports modal | مدیرمحور در UI فعلی |
| آمار | آمار و گزارش‌گیری | ✓ | — | Covered | group info stats tab | native |
| محتوا | سنجاق‌شده‌ها | ✓ | ✓ | Covered | PinController + pinned UI | message/post/poll؛ realtime |
| داشبورد | attention stats | ✓ | ✓ | Covered | /groups/{id}/najm-hoda/attention | overdue/due_soon/blocked/urgent/unassigned |
| داشبورد | management snapshot | ✓ | ✓ | Covered | NajmHodaGroupManagementSnapshotService | نشست/صورتجلسه/تصمیم/اقدام/درخواست/پین |
| فایل | مدیریت فایل مستقل گروه | — | — | Intentionally not implemented | — | در سورس فعلی capability مستقل ندارد؛ با دبیرخانه جایگزین می‌شود |
| ثبت رسمی | دبیرخانه گروه | ✓ | ✓ | Missing → Dedicated Module | Secretariat / Registry | ماژول مستقل بعدی |
| انتخابات | انتخابات سیستمی مدیر/بازرس | — | — | Intentionally excluded | system election engine | خارج از scope فعلی |

## موارد Partial یا نیازمند بررسی بعدی
1. مدیریت اعضا: کنسول مسیر native را باز می‌کند، اما capability matrix نهایی باید تفاوت دقیق اختیار مدیر و بازرس را از policy/backend اصلی استخراج و مستند کند.
2. گزارش‌ها: در UI فعلی manager-only است؛ اگر در آینده بازرس نیاز به مسیر جداگانه نظارتی داشته باشد باید policy مستقل تعریف شود.
3. انتخابات درون‌گروهی: ایجاد و مدیریت UI پوشش داده شده، ولی عملیات جزئی چرخه عمر مثل ویرایش/لغو/بستن باید در audit بعدی election module به‌صورت صریح ماتریس شوند.
4. پین‌ها: مشاهده و تغییر در backend موجود است؛ در کنسول فعلاً ورود به بخش native انجام می‌شود. در صورت نیاز می‌توان guided pin/unpin را بعداً به نجم هدا افزود.

## نتیجه ممیزی
کنسول مدیریتی اکنون بخش عمده capabilityهای واقعی مدیر/بازرس در صفحه گروه را پوشش می‌دهد و به‌جای تکثیر منطق، بر workflowهای موجود سوار است. شکاف بنیادی باقی‌مانده «دبیرخانه/Registry» است که نباید به‌عنوان file manager ساده حل شود و باید bounded module مستقل داشته باشد.
