# تصمیم TypeScript برای چت گروهی

## تصمیم

مهاجرت به TypeScript تأیید می‌شود، اما تبدیل یک‌بارهٔ runtimeهای legacy در فاز ۵ انجام نمی‌شود. مسیر انتخاب‌شده تدریجی و contract-first است تا dual-run فعلی و rollback پرچم `GROUP_CHAT_FEATURE_MODULAR_FRONTEND_V1` حفظ شود.

## دلایل

- پروژه در حال حاضر `typescript` و `tsconfig.json` ندارد و build اصلی Vite 4 مبتنی بر JavaScript است.
- sidecarهای باقی‌مانده هنوز اتصال مستقیم DOM دارند؛ تبدیل هم‌زمان زبان و معماری دامنهٔ خطا را بیش از حد بزرگ می‌کند، هرچند globalهای آن‌ها اکنون به APIهای محدود و دارای مالک تقلیل یافته‌اند.
- ماژول‌های `resources/js/group-chat` مرزهای پایدار API، feed، renderer، lifecycle و realtime را ایجاد کرده‌اند و نقطهٔ شروع امن مهاجرت‌اند.

## ترتیب مهاجرت مصوب

1. تعریف typeهای `ApiEnvelope`، `FeedEvent`، `FeedItem`، `FeedMutation` و `RealtimeDecision` بدون تغییر runtime.
2. تبدیل `api-client.js` و `realtime.js`؛ این دو ماژول کمترین وابستگی DOM را دارند.
3. تبدیل `feed.js`، `renderer.js` و `store.js` پس از تثبیت قراردادهای مرحلهٔ اول.
4. تبدیل composer/unread/actions فقط پس از فعال‌سازی کنترل‌شدهٔ frontend ماژولار.
5. نگه‌داشتن `chat-features.js` و `voice-recorder.js` به‌عنوان sidecarهای دارای API محدود تا مهاجرت تدریجی؛ `public/js/group-chat.js` بازنشسته شده و مقصد مهاجرت مستقیم نیست.

## دروازه‌های شروع

- TypeScript فقط همراه با `noEmit` type-check مستقل اضافه شود و build production را ناگهانی جایگزین نکند.
- قراردادهای DTO سمت Laravel با typeهای frontend و fixtureهای تست همگام شوند.
- هیچ `any` در typeهای قرارداد feed/API پذیرفته نشود؛ دادهٔ خارجی ابتدا `unknown` و سپس validate شود.
- هر تبدیل باید build، تست‌های Node و rollback با feature flag خاموش را حفظ کند.

## نتیجه برای فاز ۵

ارزیابی انجام و مسیر مهاجرت تصویب شد. جداسازی Blade و بازنشستگی runtime قدیمی در فاز ۵ تکمیل شدند. نصب dependency و تبدیل فایل‌ها پس از تثبیت قراردادهای UX و اجرای گیت E2E جامع انجام می‌شود؛ بنابراین فاز ۵ بدهی «تصمیم‌گیری TypeScript» ندارد و خود مهاجرت عمداً خارج از دامنهٔ این فاز است.
