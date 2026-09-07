# Runbook عملیاتی دبیرخانه EarthCoop

## نسخه S9

این سند برای عملیات production دبیرخانه است و جایگزین backup/monitoring کل EarthCoop نیست؛ دبیرخانه به DB و storage هر دو وابسته است.

---

## 1. Health Check روزمره

Snapshot سبک:

```bash
php artisan secretariat:health
```

برای audit عمیق:

```bash
php artisan secretariat:health --deep
```

برای monitoring/GameDay که باید با exit code شکست بخورد:

```bash
php artisan secretariat:health --deep --fail-on-issues
```

### در failure چه چیزهایی بررسی شوند

- missing attachment object
- attachment checksum mismatch
- integrity manifest mismatch/drift
- scanner unavailable/error counts
- database connectivity/migration state

هیچ corruption را با update مستقیم DB «رفع» نکنید. ابتدا evidence حفظ شود و علت مشخص گردد.

---

## 2. Upload / Malware Policy

تنظیمات upload دبیرخانه در config مربوط به Secretariat نگهداری می‌شوند.

Production باید:
- max size معقول محیط را تعیین کند؛
- MIME allowlist را محدود نگه دارد؛
- executable/script MIME را مجاز نکند؛
- در صورت وجود scanner واقعی، implementation `SecretariatMalwareScanner` را در container bind کند.

### قانون مهم

اگر scanner در دسترس نیست، وضعیت باید `unavailable` بماند.

**نبود scanner هرگز معادل clean نیست.**

---

## 3. Backup / Restore Drill

اسکریپت:

```bash
bash scripts/secretariat-dr-drill.sh
```

Environment لازم:

- `DB_HOST`
- `DB_PORT`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_DATABASE`

اختیاری:

- `SECRETARIAT_STORAGE_DIR`
- `SECRETARIAT_DR_OUTPUT`
- `KEEP_DRILL_DATABASE=1`

### خروجی evidence

هر run یک directory timestamped تولید می‌کند شامل:
- full `database.sql`
- SHA-256 database dump
- `secretariat-storage.tar.gz`
- SHA-256 storage archive
- لیست جدول‌های Secretariat
- CSV مقایسه row count source/restored
- manifest

### چرا full DB؟

چون Registry به users/groups/source-domainها FK واقعی دارد. restore مستقل جدول‌های Secretariat به DB خالی نماینده بازیابی واقعی نیست.

### Schedule پیشنهادی

- backup واقعی production طبق سیاست کل EarthCoop: منظم و خارج از web root
- restore drill: حداقل دوره‌ای و پیش از releaseهای پرریسک
- GameDay: پس از تغییرات schema/storage/authorization مهم

Backup بدون restore test، backup اثبات‌شده محسوب نمی‌شود.

---

## 4. Storage Incident

اگر health check missing object یا checksum mismatch گزارش کرد:

1. record/attachment id را ثبت کنید.
2. فایل یا row را حذف/overwrite نکنید.
3. audit timeline و integrity manifests را حفظ کنید.
4. آخرین backup سالم را مشخص کنید.
5. object را در محیط quarantine/forensic مقایسه کنید.
6. restore فقط با evidence و مسیر کنترل‌شده انجام شود.
7. پس از restore دوباره deep health اجرا شود.

برای نسخه رسمی، attachment جدید نباید retroactively به همان official version الصاق شود؛ amendment/versioning contract باید رعایت شود.

---

## 5. Integrity Incident

اگر integrity manifest drift گزارش شد:

- export جدید تولید نکنید تا علت روشن شود؛
- current version، attachments، contract details/signatories و manifest payload مقایسه شوند؛
- audit events بررسی شوند؛
- تغییر مستقیم DB به‌عنوان repair ممنوع است؛
- در صورت corruption storage، DR process اجرا شود؛
- در صورت defect نرم‌افزاری، fix روی branch و regression test اجباری است.

---

## 6. Permission Incident

اگر کاربر سندی را دیده یا عملیاتی را اجرا کرده که نباید:

1. ACL entryهای record بررسی شوند.
2. group membership/role/expiry بررسی شود.
3. access-sensitive audit timeline بررسی شود.
4. controller و service-level authorization هر دو بررسی شوند.
5. هیچ bypass موقت production اضافه نشود.
6. regression/penetration test قبل از deploy fix الزامی است.

S9 intentionally authorization را در serviceهای حقوقی/حساس دوباره enforce می‌کند تا caller داخلی نتواند Policy را دور بزند.

---

## 7. Legacy Assessment

قبل از هر legacy import:

```bash
php artisan secretariat:legacy-assess
```

یا:

```bash
php artisan secretariat:legacy-assess --json
```

### قواعد

- جدول `files` import source معتبر نیست.
- `ticket_attachments` فقط candidate assessment است.
- storage object باید واقعاً وجود داشته باشد.
- ticket provenance باید معتبر باشد.
- import واقعی باید mapping مقصد روشن، idempotency و human review داشته باشد.
- هیچ legacy file نباید فقط به‌خاطر وجود row به سند رسمی Registry تبدیل شود.

---

## 8. Performance

Indexهای S9 برای دو read path پرتکرار اضافه شده‌اند:

- Office pending/work queue
- Office-scoped registered ordering

Indexهای موجود ACL/dispatch/relation باید حفظ شوند.

هر index جدید آینده باید با query path واقعی یا EXPLAIN توجیه شود. افزودن index حدسی به جدول‌های audit-heavy مجاز نیست.

---

## 9. Release Checklist دبیرخانه

قبل از هر deploy مهم:

- `migrate` plan مرور شود.
- full Secretariat tests سبز باشند.
- Najm Hoda Secretariat regressions سبز باشند.
- group authorization regressions سبز باشند.
- concurrency gate سبز باشد.
- `secretariat:health --deep --fail-on-issues` در محیط مناسب اجرا شود.
- backup قابل بازیابی موجود باشد.
- برای تغییرات storage/schema، DR drill تازه وجود داشته باشد.
- legacy import در صورت وجود، ابتدا assessment و dry-run داشته باشد.

---

## 10. Merge Rule

شاخه‌های S1 تا S9 به‌صورت زنجیره‌ای توسعه یافته‌اند.

هیچ merge به `main` صرفاً به‌دلیل سبز بودن یک PR مرحله‌ای انجام نشود.

ادغام نهایی فقط باید روی integration branch امن انجام شود، تمام زنجیره در برابر baseline فعلی main دوباره validate شود، و سپس با تصمیم صریح مالک پروژه به main برسد.
