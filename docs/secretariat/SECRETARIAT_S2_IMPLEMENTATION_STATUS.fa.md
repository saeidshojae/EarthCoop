# وضعیت اجرای Phase S2 — Attachments, Relations, ACL & Basic UI

**Branch:** `agent/secretariat-s2-documents-relations-acl-ui`

**Base:** `agent/secretariat-s1-registry-core` / PR #28

**PR:** #29

## وضعیت فعلی
Backend foundation فاز S2 پیاده‌سازی اولیه شده است، اما تا عبور CI اختصاصی S2 **Gate باز** می‌ماند. Basic UI و deterministic search عمداً بعد از سبزشدن این لایه شروع می‌شوند.

## پیاده‌سازی‌شده
### Attachments
- جدول `secretariat_attachments`
- تعلق اجباری به Record
- pin شدن به RecordVersion
- storage disk/key مستقل از legacy `files`
- SHA-256 checksum
- file size / MIME / original name / uploader / upload time
- audit event `attachment_added`
- منع تغییر identity فایل پس از ثبت metadata
- منع hard-delete attachment متعلق به record رسمی
- منع افزودن retroactive attachment به Version رسمی؛ برای سند formal فایل جدید باید روی amendment version قرار گیرد

### Relations
- جدول `secretariat_relations`
- relation جهت‌دار
- taxonomy مطابق S0
- unique direction tuple
- idempotent create
- deterministic record lock order برای جلوگیری از A→B / B→A deadlock
- منع self relation
- منع cross-office relation در S2؛ policy بین دفترها طبق roadmap به S5 موکول است
- audit event `relation_added`
- منع hard-delete relation درگیر با record رسمی

### ACL
- جدول `secretariat_acl_entries`
- principalهای پایدار `user` و `group`
- permission اولیه `view`
- expires_at
- revoke بدون hard-delete
- append-only grant generations؛ re-grant پس از revoke/expiry row تازه می‌سازد
- audit events `acl_granted` و `acl_revoked`
- `restricted/confidential` در RecordPolicy از ACL صریح استفاده می‌کنند
- hook ثبت `access_sensitive` برای مشاهده confidential

## تست‌های اضافه‌شده
- `SecretariatS2AttachmentTest`
- `SecretariatS2AclTest`
- `SecretariatS2RelationTest`

## CI Gate
Workflow: `EarthCoop Secretariat S2 Validation`

باید در یک run واحد پاس شوند:
1. PHP syntax کل S1/S2
2. `migrate:fresh` روی MySQL 8
3. rollback و re-apply سه migration S2
4. کل `tests/Feature/Secretariat` شامل regressionهای S1
5. سه دور × 12 process concurrency شماره ثبت S1
6. `MessageAuthorizationTest`
7. `GroupRoleManagementTest`

## باقی‌مانده S2 پس از Backend Gate
- quick deterministic search
- Office dashboard
- New Record UI
- Draft / Pending Approval / Registered lists
- search by registry number/title/type/date
- Record detail page
- version/attachment/relation/timeline presentation
- permission-aware download/view path برای attachment
- confidential access audit در HTTP read boundary

## Merge Safety
این branch مستقیماً به `main` یا roadmap merge نمی‌شود. PR #29 روی branch S1 قرار دارد و تا تکمیل Gate S2 Draft باقی می‌ماند.
