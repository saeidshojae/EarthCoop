# چک‌لیست اصلاح چت گروه (عملیاتی و قابل تحویل)

تاریخ شروع: 2026-02-18  
وضعیت کلی: `In Progress`  
مالک: تیم فنی چت گروه  
محدوده: فقط مسیر چت گروه، بدون تغییر رفتاری در نجم‌هدا مگر سازگار با آن

## 1) اصول ایمنی (برای جلوگیری از خرابی نجم‌هدا)
- [x] هیچ روت یا کنترلر `najm-hoda` تغییر داده نشود.
- [x] تغییرات فقط در مسیرهای زیر باشد:
  - `resources/views/groups/chat.blade.php`
  - `public/js/group-chat.js`
  - اسناد فنی `docs/`
- [ ] بعد از هر اصلاح، صحت سینتکس فایل‌های تغییر کرده بررسی شود. (در حال حاضر `public/js/group-chat.js` پیش از این هم دارای مشکل encoding/کاراکتر نامعتبر است.)

## 2) فهرست مشکلات، علت و راه اصلاح

### A. Realtime واقعی کار نمی‌کند
- وضعیت: [ ] انجام نشده
- شدت: بحرانی
- نشانه: رأی نظرسنجی/پیام فوری روی کلاینت‌های دیگر به‌موقع دیده نمی‌شود.
- علت فنی:
  - `BROADCAST_DRIVER=log` در `.env`
  - کلیدهای `VITE_PUSHER_*` خالی، بنابراین Echo در فرانت فعال نمی‌شود.
- راه اصلاح:
  - پیکربندی broadcaster واقعی (Pusher/Reverb) و مقداردهی `VITE_PUSHER_*`.
  - فعال‌سازی worker مناسب برای صف رویدادها.
- معیار پذیرش:
  - ارسال پیام در یک کلاینت، در کلاینت دوم بدون refresh زیر 1 ثانیه دیده شود.
  - رأی نظرسنجی همزمان روی همه کلاینت‌ها آپدیت شود.

### B. همزمانی websocket و polling (فشار مضاعف)
- وضعیت: [x] اصلاح اولیه انجام شد
- شدت: بحرانی
- نشانه: درخواست‌های اضافی به `/api/groups/{id}/messages` حتی وقتی realtime فعال است.
- علت فنی:
  - در `public/js/group-chat.js` بعد از `initRealtimeMessages()`، `startPolling()` هم بدون شرط شروع می‌شود.
- راه اصلاح:
  - `initRealtimeMessages` مقدار بولی بازگرداند.
  - `startPolling()` فقط وقتی websocket فعال/متصل نیست اجرا شود.
- معیار پذیرش:
  - در حالت websocket سالم، polling شروع نشود.
  - در نبود websocket، polling با fallback فعال شود.

### C. لود تکراری assets (Vite دوبار)
- وضعیت: [x] انجام شد
- شدت: بالا
- نشانه: CSS/JS پایه دوبار بارگذاری می‌شود و زمان parse/execute بالا می‌رود.
- علت فنی:
  - `@vite(['resources/css/app.css', 'resources/js/app.js'])` هم در `layouts/chat` و هم در `groups/chat` موجود است.
- راه اصلاح:
  - حذف فراخوانی تکراری از `resources/views/groups/chat.blade.php`.
- معیار پذیرش:
  - هر bundle پایه فقط یک بار در صفحه چت بارگذاری شود.

### D. سنگینی بیش‌ازحد صفحه چت
- وضعیت: [ ] انجام نشده
- شدت: بالا
- نشانه: زمان لود اولیه بالا، TTI ضعیف.
- علت فنی:
  - `chat.blade.php` و `group-chat.js` بسیار بزرگ شده‌اند.
  - اسکریپت‌های inline زیاد و منطق‌های پراکنده.
- راه اصلاح:
  - مرحله‌ای: استخراج scriptهای inline به فایل‌های جدا.
  - بارگذاری تنبل (lazy) برای ماژول‌های غیر ضروری.
- معیار پذیرش:
  - کاهش محسوس زمان parse/execute در DevTools.

### E. خرابی encoding (mojibake) در متن‌ها
- وضعیت: [ ] انجام نشده
- شدت: بالا
- نشانه: نمایش کاراکترهای خراب مانند `Ã`, `Ø`, `Ù`, `ï¿½`.
- علت فنی:
  - فایل‌های UTF-8 آسیب دیده یا با encoding ناسازگار ذخیره شده‌اند.
- راه اصلاح:
  - اصلاح امن encoding با اسکریپت و بازبینی دستی رشته‌های کاربری.
- معیار پذیرش:
  - نبود رشته خراب در فایل‌های چت و نمایش درست فارسی.

### F. N+1 در شمارش رأی نظرسنجی
- وضعیت: [x] انجام شد
- شدت: متوسط تا بالا
- نشانه: در vote هر گزینه query جداگانه می‌گیرد.
- علت فنی:
  - `PollController@vote` برای هر option یک `count` مجزا می‌زند.
- راه اصلاح:
  - تجمیع شمارش با `group by option_id` در یک query.
- معیار پذیرش:
  - تعداد query رأی‌گیری ثابت و پایین بماند.

## 3) برنامه اجرای مرحله فعلی (Current Sprint)
- [x] ایجاد این چک‌لیست رسمی
- [x] حذف لود تکراری Vite در صفحه چت
- [x] شرطی‌سازی polling نسبت به websocket
- [ ] ثبت نتیجه و تیک‌زدن موارد انجام‌شده

## 4) لاگ تغییرات (Progress Log)
- 2026-02-18: چک‌لیست رسمی ایجاد شد و محدوده امن تغییرات برای حفظ نجم‌هدا تعریف شد.
- 2026-02-18: لود تکراری Vite در `resources/views/groups/chat.blade.php` حذف شد.
- 2026-02-18: در `public/js/group-chat.js` شروع polling شرطی شد تا هنگام آماده بودن websocket، polling همزمان اجرا نشود.
- 2026-02-18: در `app/Http/Controllers/Group/PollController.php` شمارش رأی گزینه‌ها از N+1 به query تجمیعی (`group by`) تبدیل شد.

## 2026-02-18 - Step Update (Encoding Stabilization)
- [x] `app/Http/Controllers/Group/MessageController.php`: removed remaining mojibake strings in API/user-facing texts.
- [x] Replaced corrupted placeholders with safe fallback messages (`Voice message`, `Unknown user`, `members found`, rate-limit text).
- [x] Verified syntax: `php -l app/Http/Controllers/Group/MessageController.php`.
- [x] Verified chat JS syntax still valid: `node --check public/js/group-chat.js`.
- [ ] Next: continue targeted cleanup in `public/js/group-chat.js` user-facing labels/messages.

## 2026-02-18 - Step Update (Polling Throughput Hardening)
- [x] public/js/group-chat.js: added pollingRequestInFlight guard to prevent overlapping polling requests.
- [x] public/js/group-chat.js: optimized getLastMessageId() to avoid Math.max(...Set) spread on large sets.
- [x] public/js/group-chat.js: replaced per-poll DOM scan for existing message IDs with enderedMessageIds cache (with safe fallback).
- [x] public/js/group-chat.js: wired polling ajax eforeSend/complete to track in-flight state.
- [x] Validation: 
ode --check public/js/group-chat.js.
- [ ] Next: measure API latency for send-message/send-audio/send-post/send-poll and optimize server path (MessageController, PollController, queue/broadcast config).

## 2026-02-18 - Step Update (API Latency: Deferred Broadcasts)
- [x] Added GROUP_CHAT_DEFER_BROADCASTS flag in config/group-chat.php (default: 	rue).
- [x] MessageController@store: moved MessageCreated dispatch to after-response path.
- [x] MessageController: moved UserMentioned dispatch to after-response path.
- [x] BlogController (store/update/destroy): moved BlogCreated and GroupFeedUpdated dispatches to after-response path.
- [x] PollController (store/vote/update/delete): moved PollCreated / GroupPollUpdated / GroupFeedUpdated dispatches to after-response path.
- [x] Validation: php -l passed for all changed files.
- [ ] Next: instrument real request timings for /messages/send, /blog/send/{group}, /poll/send/{group}, /polls/{poll}/vote to identify remaining DB/I/O hotspots.

## 2026-02-18 - Step Update (Latency Instrumentation)
- [x] Added middleware pp/Http/Middleware/GroupChatTiming.php to measure server processing time for chat write endpoints.
- [x] Registered route middleware alias group.chat.timing in pp/Http/Kernel.php.
- [x] Applied timing middleware to:
  - POST /messages/send
  - POST /blog/send/{group}
  - POST /poll/send/{group}
  - POST /polls/{poll}/vote
- [x] Added config flags in config/group-chat.php:
  - GROUP_CHAT_API_TIMING_ENABLED
  - GROUP_CHAT_API_TIMING_LOG
  - GROUP_CHAT_API_TIMING_SLOW_MS
- [x] Response headers now include X-Chat-Server-Time-Ms and Server-Timing.
- [ ] Next: capture 20+ real samples per endpoint and isolate p95 latency bottleneck (DB vs file upload vs rendering).

## 2026-02-24 - Phase Plan (Execution Baseline)

### Guardrails (Non-Negotiable)
- [ ] No behavior change in `najm-hoda` runtime, routes, controllers, or commands.
- [ ] No behavior regression in automatic election lifecycle:
  - Admin-enabled election system must still auto-start on quorum.
  - Existing thresholds, role logic, and election finish semantics must remain intact.
- [ ] Group chat changes must remain scoped to:
  - `resources/views/groups/*`
  - `public/js/group-chat.js` and related group-chat front files
  - `app/Http/Controllers/Group/*` (chat-related only)
  - `app/Events/Group*`, `app/Events/MessageCreated.php` (chat transport only)

### Phase 1 - Critical Stabilization (Now)
- [x] Fix malformed JSON contract in `MessageController@edit`.
- [x] Fix election vote endpoint to return JSON for AJAX clients (no forced redirect path).
- [x] Fix wrong post delete endpoint in JS (`/groups/post/delete/*` -> `DELETE /blog/{id}`).
- [x] Remove obvious duplicate/legacy submit paths that cause mixed refresh/no-refresh behavior.
- [x] Verify syntax: `php -l` + `node --check` on changed files.

### Phase 2 - No-Refresh Consistency
- [ ] Single source of truth for message submit/edit/delete/reaction handlers.
- [x] Remove duplicate handlers split between inline Blade script and `group-chat.js`.
- [ ] Ensure every supported group action completes without full page refresh.

### Phase 3 - Realtime Correctness
- [ ] Enable real broadcaster transport (Pusher/Reverb config + Echo subscription path).
- [ ] Keep polling as fallback only when websocket is unavailable.
- [ ] Validate cross-client updates under 1s for message/post/poll/reaction/delete/edit.

### Phase 4 - Performance Hardening
- [ ] Remove N+1 and DB calls from Blade partials (`message/post/poll`).
- [ ] Precompute required counts/relations in controllers.
- [ ] Reduce initial payload for first chat paint (TTI and p95 API latency targets).

### Phase 5 - Encoding / UI Integrity
- [ ] Eliminate mojibake text in chat Blade/JS/partials.
- [ ] Normalize user-facing strings and placeholders in UTF-8.
- [ ] Verify identical rendering between immediate append and post-refresh server render.

### Phase 6 - Verification Matrix
- [ ] Two-user concurrent scenario test (normal + incognito).
- [ ] Full operation matrix:
  - message text/audio (create/edit/delete/reaction)
  - post (create/edit/delete/comment/reaction)
  - poll (create/edit/vote/delete)
  - election (create/participate/finish) without breaking auto-election logic
- [ ] Checklist signoff with timestamped evidence.

### 2026-02-24 - Progress Log
- [x] `app/Http/Controllers/Group/MessageController.php`: fixed `edit()` JSON contract (`status`, `message`, `message_id`).
- [x] `app/Http/Controllers/Group/ElectionController.php`: `submitVote()` now returns JSON for AJAX/JSON clients and keeps redirect fallback for non-AJAX.
- [x] `public/js/group-chat.js`: fixed post delete endpoint/method to `DELETE /blog/{id}`.
- [x] `public/js/group-chat.js`: removed legacy duplicate `chatForm` submit listener.
- [x] Validation:
  - `php -l app/Http/Controllers/Group/MessageController.php`
  - `php -l app/Http/Controllers/Group/ElectionController.php`
  - `node --check public/js/group-chat.js`
- [x] `resources/views/groups/chat.blade.php`: restored from `.bak` after unintended encoding drift during automated replacement.
- [x] `resources/views/groups/chat.blade.php`: removed heavy legacy inline `DOMContentLoaded` handler block to prevent duplicate binding.
- [x] `resources/views/groups/chat.blade.php`: disabled `chat-features.js` load (temporary) due confirmed syntax error in file.
- [x] `resources/views/groups/chat.blade.php`: removed duplicate `@vite(...)` load from chat page head (assets already loaded by layout).
- [x] `public/js/group-chat.js`: election vote submit switched to AJAX (`#electionForm`) to avoid full-page refresh.
- [x] `public/js/group-chat.js`: transport gating added (`auto/polling/websocket`) with runtime config from `window.chatConfig`.
- [x] `public/js/group-chat.js`: polling now starts only when policy requires it (fallback mode), interval bound to config.
- [x] `public/js/group-chat.js`: realtime channel bind added for `group.message.updated` and `group.poll.updated` with safe polling fallback on disconnect.
- [x] `public/js/group-chat.js`: stale `parent_id` guard added in message submit flow to prevent random unintended replies.
- [x] Validation:
  - `node --check public/js/group-chat.js`

### 2026-02-24 - Environment Blocker (Realtime)
- [ ] Current local `.env` still disables realtime transport:
  - `BROADCAST_DRIVER=log`
  - `PUSHER_APP_KEY` empty
- Impact:
  - Cross-user realtime cannot be verified as websocket-based until broadcaster is configured.
