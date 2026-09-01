# چک‌لیست Review فاز S1 دبیرخانه

- [ ] migrations روی MySQL هدف با `migrate:fresh` اجرا شوند.
- [ ] rollback کامل S1 بدون FK error اجرا شود.
- [ ] تست‌های `tests/Feature/Secretariat/*` سبز شوند.
- [ ] regression تست‌های GroupPolicy/Group chat authorization سبز بمانند.
- [ ] ثبت همزمان دو رکورد در یک office/year/family شماره تکراری تولید نکند.
- [ ] retry عملیات register sequence را دوباره افزایش ندهد.
- [ ] raw class-name در `scope_type/source_type` ذخیره نشود.
- [ ] record رسمی با update مستقیم title/subject/registry identity قابل overwrite نباشد.
- [ ] official version قابل update/delete نباشد.
- [ ] audit event قابل update/delete نباشد.
- [ ] amendment تأییدنشده current رسمی را تغییر ندهد.
- [ ] stale amendment قابل approval نباشد.
- [ ] manager گروه A نتواند رکورد گروه B را register کند.
- [ ] ordinary member نتواند register کند.
- [ ] `restricted/confidential` تا S2 برای non-admin default-deny بماند.
- [ ] هیچ controller/UI/attachment/correspondence خارج از scope S1 وارد diff نشده باشد.
- [ ] PR همچنان Draft بماند تا schema/tests review و CI کامل شود.
