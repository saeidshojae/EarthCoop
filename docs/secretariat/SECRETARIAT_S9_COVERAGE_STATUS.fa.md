# وضعیت پوشش S9 دبیرخانه EarthCoop

## Phase S9 — Production Hardening & Legacy Migration

این سند مرجع وضعیت نهایی S9 است.

Base قطعی S9:

`11cdf409c637be77ca13e477f6941aa56f95ec1a`

آخرین head کدی که پیش از closeout مستندات روی MySQL واقعی سبز شده:

`297d597b41d29118800b2c8cabfcd6d1540c89c5`

Validation:

- run #70
- run id `32249048896`
- نتیجه: SUCCESS

---

## 1. Performance / Index Audit — DONE

### پوشش
- audit مسیرهای واقعی read به‌جای افزودن index حدسی
- حفظ indexهای موجود ACL، Dispatch و Relation
- index جدید برای Office work queue:
  - `office_id, status, updated_at, id`
- index جدید برای Office-scoped registry ordering:
  - `office_id, registered_at, id`

### تست
`SecretariatS9PerformanceIndexTest`

### تصمیم
Full-text redesign در S9 انجام نشد؛ retrieval موجود authorization-aware باقی می‌ماند و هر تغییر full-text/semantic باید contract امنیتی S6 را حفظ کند.

---

## 2. Large-file / Upload Boundary — DONE

### پوشش
- server-side configurable maximum bytes
- server-detected MIME allowlist
- script/executable payloadها در policy پیش‌فرض مجاز نیستند
- checksum پیش از commit
- storage compensation در failure

### تست
`SecretariatS9AttachmentHardeningTest`

---

## 3. Malware Scanner Adapter — DONE

### پوشش
- `SecretariatMalwareScanner` provider-neutral contract
- implementation پیش‌فرض `UnavailableSecretariatMalwareScanner`
- نبود provider هرگز `clean` گزارش نمی‌شود
- `infected` و `error` upload را قبل از persistence رد می‌کنند
- نتیجه scan موفق/ناموجود در `secretariat_attachment_scans` append-only ثبت می‌شود

### تصمیم عملیاتی
Production باید provider واقعی scanner را bind کند اگر محیط اجازه دهد. تا آن زمان وضعیت صریح `unavailable` قابل مشاهده و قابل مانیتور است.

---

## 4. Storage Lifecycle / Integrity Health — DONE

### پوشش
- snapshot سبک DB-only برای مانیتورینگ مکرر
- deep audit bounded برای storage object presence/checksum
- integrity manifest drift detection
- scan-unavailable visibility
- command:

`php artisan secretariat:health`

Deep/GameDay:

`php artisan secretariat:health --deep --fail-on-issues`

### تست
`SecretariatS9OperationalHealthTest`

---

## 5. Permission Penetration / Confused Deputy — DONE

### یافته مهم
برخی serviceهای S8 actor را برای audit دریافت می‌کردند، اما authorization را در خود service دوباره enforce نمی‌کردند.

### اصلاح
service-level authorization برای:
- ACL grant/revoke
- Contract formality mutations
- Integrity manifest generation
- Signature/seal evidence
- Retention assignment
- Legal hold place/release
- Export package generation

Authorization داخل transaction/lock نیز در مسیرهای حساس re-check می‌شود.

### تست
`SecretariatS9PermissionPenetrationTest`

### invariant
caller داخلی اشتباه نباید بتواند با فراخوانی مستقیم domain service از Policy عبور کند.

---

## 6. Concurrency — DONE / REGRESSION PRESERVED

Registry numbering concurrency همچنان در authoritative CI با سه دور × ۱۲ worker واقعی اثبات می‌شود.

S9 این invariant را نشکسته است.

---

## 7. Backup / Restore Drill — DONE

### اصل معماری
Secretariat به users/groups/source domains FK واقعی دارد؛ بنابراین dump صرفاً `secretariat_*` یک backup مستقل و قابل restore نیست.

### راهکار
`scripts/secretariat-dr-drill.sh`

Drill:
1. تمام DB transactional را با `mysqldump --single-transaction` snapshot می‌کند.
2. SHA-256 dump را ثبت می‌کند.
3. `storage/app/secretariat` را archive و checksum می‌کند.
4. یک DB موقت می‌سازد.
5. dump کامل را restore می‌کند.
6. row-count تمام جدول‌های `secretariat_*` را source ↔ restored مقایسه می‌کند.
7. evidence manifest تولید می‌کند.
8. DB موقت را پاک می‌کند مگر صراحتاً برای inspection نگه داشته شود.

### تست واقعی CI
`SecretariatS9DisasterRecoveryDrillTest`

این تست روی MySQL واقعی همان dump/restore را اجرا می‌کند؛ mock نیست.

---

## 8. Observability — DONE

Operational health به provider خارجی وابسته نیست و خروجی deterministic دارد.

Production monitoring می‌تواند command health را از cron/monitor اجرا کند و failure code را مصرف کند.

Metrics پایه:
- attachment/scan state
- missing storage objects
- checksum mismatches
- integrity manifest drift
- operational DB counts

---

## 9. Legacy Migration Assessment — DONE / IMPORT DELIBERATELY NOT AUTOMATIC

### legacy `files`
Schema واقعی فقط `id + timestamps` دارد.

فاقد:
- owner
- storage path
- MIME
- provenance

پس:

**`files` به‌صورت خودکار منبع دبیرخانه نیست و import نمی‌شود.**

### `ticket_attachments`
دارای:
- ticket/comment provenance
- uploader
- file path
- file name
- MIME
- size

اما import فقط بعد از assessment مجاز است.

Command:

`php artisan secretariat:legacy-assess`

JSON:

`php artisan secretariat:legacy-assess --json`

Candidate فقط وقتی گزارش می‌شود که ticket provenance و storage object واقعی قابل اثبات باشند.

### تست
`SecretariatS9LegacyAssessmentTest`

### تصمیم
S9 هیچ legacy row را خودکار به truth رسمی Registry تبدیل نمی‌کند. mapping/import واقعی باید یک migration job صریح، idempotent و human-reviewed باشد.

---

## 10. Group/Najm Hoda Regressions — GREEN

Authoritative run #70 اثبات کرده:
- full Secretariat suite PASS
- full Najm Hoda Secretariat suite PASS
- Registry concurrency PASS
- Group authorization regressions PASS

---

## 11. S9 Gate

Roadmap Gate:

> production readiness مستقل دبیرخانه + GameDay برای permission/integrity/restore.

پوشش:
- Permission → penetration tests
- Integrity → operational deep health corruption tests
- Restore → real MySQL dump/restore drill
- Storage → checksum/object verification
- Malware boundary → adapter + explicit unavailable state
- Performance → targeted index audit
- Legacy → assessment-first, no unsafe automatic import

از منظر Domain/Automated Gate:

**S9 CLOSED**

آخرین مرحله پس از ثبت این سند:

یک documentation-head CI باید روی آخرین commit همین branch دوباره PASS شود.

---

## 12. مواردی که عمداً خارج از Definition of Done این فاز باقی می‌مانند

اینها defect بسته‌نشده S9 محسوب نمی‌شوند:
- انتخاب vendor خاص antivirus/malware scanner
- ساخت import واقعی ticket attachments بدون تصمیم mapping مقصد
- full-text redesign مستقل
- controlled redaction (طبق roadmap از S8 آینده‌نگر است)
- merge نهایی به main

هیچ‌کدام نباید بدون تصمیم صریح و evidence مناسب به Registry truth تبدیل شوند.
