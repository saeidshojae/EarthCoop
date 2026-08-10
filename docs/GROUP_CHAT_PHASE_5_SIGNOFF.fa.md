# گزارش پذیرش فاز ۵ — بازسازی فرانت‌اند چت گروهی

تاریخ پذیرش: ۱۴۰۵/۰۵/۱۹ (۲۰۲۶-۰۸-۱۰)

## نتیجه

چهار معیار معماری بازِ فاز ۵ تکمیل شدند. runtime قدیمی `public/js/group-chat.js` بازنشسته شده و فقط یک placeholder سازگاری باقی مانده است. مسیر اجرایی اصلی اکنون از ماژول‌های `Composer`، `Feed`، `Realtime`، `Unread` و `Actions` در `resources/js/group-chat` عبور می‌کند.

Store منبع canonical وضعیت UI است و Feed/Renderer مشترک، initial hydration، optimistic create، polling fallback و رویدادهای WebSocket را از یک مرز یکسان عبور می‌دهد. listenerها و timerهای صفحه به Lifecycle تعلق دارند و هنگام خروج از صفحه آزاد می‌شوند. APIهای global باقی‌مانده محدود، نام‌دار و دارای مالک مشخص‌اند.

## شواهد پذیرش

- `public/js/group-chat.js` دیگر runtime فعال، listener، timer یا mutation مسیر feed ندارد.
- ارسال پیام و پست در Composer، عملیات مدیریتی در Actions/Operations، unread در Unread و همگام‌سازی آنلاین/fallback در Realtime Runtime مالکیت یکتا دارند.
- renderer پیام و registry رندر feed، مسیر initial/optimistic/polling/WebSocket را یکپارچه می‌کنند.
- تست source contract به‌صورت بازگشتی همهٔ partialها و modalهای گروه را برای `addEventListener` خام، timer خام و guardهای global قدیمی کنترل می‌کند.
- جریان درخواست چت با schema فعلی هم‌راستا شد و دیگر به migration حذف‌شدهٔ `request_to_group` وابسته نیست.

## نتایج تست

- `npm run test:group-chat`: ۴۸ از ۴۸ تست پاس.
- `HtmlSanitizerTest`: ۲ تست پاس.
- `VoiceMessageFlowTest`: ۵ تست پاس.
- `MessageAuthorizationTest`: ۸ تست پاس.
- `FeedCursorTest`: ۶ تست پاس.
- `ApiReliabilityTest`: ۳ تست پاس.
- `ChatRequestFlowTest`: ۶ تست پاس.
- مجموع تست‌های PHP هدفمند: ۳۰ تست پاس.
- build تولید و cache کردن viewها در گیت نهایی اجرا و نتیجه در گزارش تحویل ثبت می‌شود.

## وضعیت E2E مرورگری

E2E تعاملی pass گزارش نشده است. سرور Laravel روی `127.0.0.1:8000` راه‌اندازی شد، اما ابزار Browser برای شروع به Node حداقل `22.22.0` نیاز دارد و runtime پیش‌فرض سیستم `22.11.0` است. بنابراین آزمون مرورگری «blocked/اجرا نشده» است؛ تست‌های source contract و integration جایگزین موقت‌اند و معادل E2E دوکلاینتی نیستند.

## کامیت‌های اصلی این مرحله

- `6bb118df` تا `1812eaa5`: انتقال مالکیت unread، composer، renderer، realtime، عملیات، modalها و حذف runtimeهای legacy.
- `f94e28da`: تثبیت قرارداد Lifecycle کل templateها و اصلاح fixture جریان درخواست چت.

## جمع‌بندی

پذیرش معماری فاز ۵ بسته است و معیارهای master plan علامت‌گذاری شدند. مانع E2E یک محدودیت ابزار محلی است و باید پس از ارتقای Node پیش‌فرض یا تنظیم `NODE_REPL_NODE_PATH` با سناریوی ورود مجدد و دوکلاینتی دوباره اجرا شود.
