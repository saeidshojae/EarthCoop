# وضعیت پوشش Phase S8 — Contracts, Formality & Integrity

## مبنا
این سند وضعیت پیاده‌سازی S8 را نسبت به `SECRETARIAT_MASTER_ROADMAP.fa.md` ثبت می‌کند.

Head کد پیش از این سند: `dbd9d11be4a7f5510f1c24605b826b51fc95e4ec`
Validation مرجع: run #56 / `32233370115` — SUCCESS.

## Coverage Matrix

| بند Roadmap S8 | وضعیت | پیاده‌سازی |
|---|---:|---|
| Contract/MOU specialized metadata | ✅ | `SecretariatContractVersionDetail` + `SecretariatContractService` |
| parties/signatories | ✅ | `SecretariatParty` موجود + `SecretariatContractSignatory` نسخه‌محور |
| effective/expiry/renewal | ✅ | metadata نسخه‌محور با validation و immutability پس از رسمی‌شدن |
| amendment chain | ✅ | هر amendment یک `SecretariatRecordVersion` جدید و snapshot حقوقی مستقل دارد؛ v1 overwrite نمی‌شود |
| signatures/seals adapter | ✅ | `SecretariatSignatureVerificationAdapter` + `SecretariatSignatureService`; provider-specific legal signature خارج از core باقی می‌ماند |
| document integrity verification | ✅ | canonical manifest + SHA-256 + verification against current official package |
| export package + manifest/checksums | ✅ | ZIP canonical export شامل package manifest، integrity manifest، record-version snapshot، attestations و attachments با checksum verification |
| retention policy | ✅ | `SecretariatRetentionAssignment` + `SecretariatRetentionService` |
| legal hold | ✅ | `SecretariatLegalHold` + hold/release controls; retention disposition تحت hold مسدود می‌شود |
| controlled redaction | ⏳ | طبق خود Roadmap «در آینده» و جزو Gate S8 نیست |

## Formality invariants

- `contract`, `memorandum_of_understanding`, `agreement` همان `SecretariatRecord` هستند؛ repository موازی ساخته نشده است.
- truth حقوقی مفاد به Version متصل است، نه Record mutable.
- نسخه contract-like بدون details و signatory snapshot رسمی نمی‌شود.
- metadata و signatoryهای نسخه رسمی immutable هستند.
- amendment بدون snapshot حقوقی خودش official نمی‌شود.
- signature attestation جای source-of-authority حقوقی را نمی‌گیرد؛ provider adapter فقط verification evidence ثبت می‌کند.
- integrity manifest append-only است و payload canonical checksum دارد.
- export قبل از تولید، integrity و checksum attachmentها را دوباره verify می‌کند.
- legal hold hard-delete یا silent disposition تاریخچه را مجاز نمی‌کند.

## Validation evidence

Run #56 / `32233370115` روی PHP 8.2 + MySQL 8:

- syntax gate: PASS
- `migrate:fresh`: PASS، شامل 4 migration فاز S8
- Secretariat suite: **118 tests / 445 assertions PASS**
- Najm Hoda Secretariat suite: **54 tests / 457 assertions PASS**
- ExecutionBoundary regression: **7 tests / 46 assertions PASS**
- registry-number concurrency: **3 × 12 parallel workers PASS**
- GroupChat authorization: **23 tests / 121 assertions PASS**
- GroupRoleManagement: **4 tests / 16 assertions PASS**

## Gate S8
Acceptance Gate Roadmap:

> قرارداد با amendment و سابقه کامل بدون از دست رفتن نسخه‌های قبلی مدیریت شود.

این Gate با `SecretariatS8ContractFormalityTest` اثبات شده است: نسخه اول رسمی immutable می‌ماند؛ amendment دوم تا زمان داشتن formality snapshot مستقل رسمی نمی‌شود؛ و پس از approval، current version تغییر می‌کند بدون تغییر metadata نسخه اول.

## مرزهای آگاهانه

- هیچ provider امضای حقوقی خاص کشور در core hard-code نشده است.
- ZIP canonical package فرمت export فعلی است؛ renderer نمایشی PDF می‌تواند بعداً به‌صورت adapter اضافه شود بدون تغییر integrity truth یا archive package.
- controlled redaction طبق Roadmap برای آینده نگه داشته شده است.

با این شواهد، S8 از نظر Domain/Automated Gate بسته است و S9 باید فقط از آخرین head سبز همین شاخه منشعب شود.
