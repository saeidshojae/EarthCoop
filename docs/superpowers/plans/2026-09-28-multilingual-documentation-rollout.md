# Multilingual Documentation Rollout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تبدیل نظام اسناد EarthCoop به یک زنجیره سه‌زبانه FA/EN/AR با یک منبع حقیقت، محتوای محصول منطبق با واقعیت، مرکز اسناد اختصاصی چندزبانه و خروج تدریجی از Mintlify.

**Architecture:** اجرای Spec به چهار زیرپروژه مستقل اما وابسته شکسته می‌شود. ابتدا قرارداد داده و رجیستری در `EarthCoop-docs` تثبیت می‌شود؛ سپس محتوای انگلیسی/عربی بر اساس شواهد محصول ممیزی و بازنویسی می‌شود؛ بعد مرکز اسناد اختصاصی سه‌زبانه می‌شود؛ و در پایان Mintlify از مسیر اصلی Production خارج می‌شود. هیچ مرحله‌ای نباید Source of Truth را به Renderer منتقل کند.

**Tech Stack:** GitHub, JSON Schema Draft 2020-12, Node.js 22 / `node:test`, Markdown/MDX, Laravel 9/PHP 8.2 برای قرارداد لینک‌های سایت اصلی، و فناوری فعلی مرکز اسناد پس از شناسایی repository/deployment آن.

**Spec:** `docs/superpowers/specs/2026-09-28-multilingual-documentation-architecture-design.md`

## Global Constraints

- `EarthCoop-docs` تنها Source of Truth محتوایی/رجیستری باشد.
- فارسی برای اسناد حقوقی و مرجع، زبان مرجع پیش‌فرض است مگر سند خلاف آن را تصریح کند.
- ترجمه EN/AR از متن مرجع تولید شود؛ AI حق استنتاج آزاد Feature یا حکم تازه ندارد.
- وضعیت ترجمه فقط `current`, `needs_review`, `outdated`, `not_translated` باشد.
- وضعیت قابلیت راهنمای محصول فقط `available`, `in_development`, `planned` باشد.
- هیچ Feature بدون شاهد معتبر Production/Code/approved spec به‌عنوان `available` معرفی نشود.
- انتشار/نمایش/ترجمه هیچ اثر حقوقی جدید ایجاد نمی‌کند.
- Mintlify تا Gate نهایی فقط Renderer/Host موقت یا fallback است.
- URL نهایی مرکز اسناد باید namespace واقعی `/fa/...`, `/en/...`, `/ar/...` داشته باشد.
- تغییرات Production فقط با PR، CI سبز و rollback مشخص انجام شوند.

## Review Focus

- تغییر نسخه فارسی یک سند باید renditionهای EN/AR وابسته را stale کند، نه اینکه silently `current` بمانند.
- نبود ترجمه نباید باعث fallback اشتباه به متن زبان دیگر با برچسب غلط شود.
- صفحات راهنمای قدیمی نباید Feature برنامه‌ریزی‌شده را Available نمایش دهند.
- تغییر Renderer یا DNS نباید شناسه سند، status حقوقی یا URL canonical داخلی را از بین ببرد.
- RTL/LTR و deep-link مستقیم به `/fa|en|ar/...` باید بدون وابستگی به state قبلی مرورگر درست کار کند.

---

## برنامه‌های اجرایی وابسته

### Plan A — Registry & Translation Contract

مسیر: `docs/superpowers/plans/2026-09-28-docs-registry-translation-contract.md`

خروجی مستقل: schemaVersion 2، identity واحد سند، renditions سه‌زبانه، stale detection، glossary contract و CI validator.

**Gate خروج:** تمام تست‌های `EarthCoop-docs` سبز و manifest موجود بدون تغییر اثر حقوقی migrate شده باشد.

### Plan B — Product Documentation Truth Audit

مسیر: `docs/superpowers/plans/2026-09-28-product-docs-truth-audit.md`

خروجی مستقل: inventory کامل انگلیسی، حذف `NewEarthCoop` از محتوای عمومی، بازنویسی صفحات بر مبنای واقعیت و برچسب وضعیت Feature، سپس ترجمه کنترل‌شده عربی.

**Gate ورود:** Plan A merged باشد.

**Gate خروج:** هیچ ادعای Available بدون evidence ثبت‌شده و هیچ occurrence عمومی نام قدیمی باقی نماند.

### Plan C — Multilingual Docs Center

مسیر: `docs/superpowers/plans/2026-09-28-docs-center-multilingualization.md`

خروجی مستقل: مرکز اسناد اختصاصی با language switcher واقعی، مسیرهای `/fa`, `/en`, `/ar`, full-text search زبان‌محور و fallback آگاه از status ترجمه.

**Gate ورود:** source repository و deployment owner مرکز اسناد شناسایی و در plan concrete ثبت شود؛ Plan A merged باشد.

**Gate خروج:** UAT سه‌زبانه روی staging و contract tests deep-link/search/navigation سبز باشد.

### Plan D — Mintlify Transition & Cutover

مسیر: `docs/superpowers/plans/2026-09-28-mintlify-transition-cutover.md`

خروجی مستقل: docs.earthcoop.ir روی مرکز اختصاصی، Mintlify فقط fallback/preview یا حذف‌شده، redirect و rollback تأییدشده.

**Gate ورود:** Plan B و C کامل، crawl/link audit سبز، backup DNS/deployment و rollback rehearsal موفق.

---

## ترتیب اجرا

`Plan A → Plan B` و هم‌زمان پس از A، `Plan C` می‌تواند آغاز شود. `Plan D` فقط پس از پایان B و C اجرا می‌شود.

هیچ worker مجاز نیست برای سرعت، Plan D را پیش از Gateهای آن آغاز کند.