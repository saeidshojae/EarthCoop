# Docs Center Multilingualization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** تبدیل مرکز اسناد اختصاصی EarthCoop از وضعیت تک‌زبانه فعلی به renderer سه‌زبانه واقعی FA/EN/AR که مستقیماً قرارداد manifest v2 را مصرف کند.

**Architecture:** این زیرپروژه دو Gate دارد. ابتدا باید repository/deployment فعلی مرکز اسناد اختصاصی شناسایی و مالکیت آن مستند شود؛ سپس plan اجرایی concrete با exact file paths همان codebase نوشته و تأیید شود. پیاده‌سازی قبل از این Gate ممنوع است، زیرا در repositoryهای فعلی Source Code مرکز اختصاصی هنوز به‌صورت قابل اتکا شناسایی نشده و حدس زدن مسیرها خلاف قرارداد پروژه است.

**Tech Stack:** فناوری واقعی مرکز اسناد پس از Task 1 ثبت می‌شود؛ قرارداد داده JSON manifest v2 است. رابط باید RTL/LTR، full-text search و routeهای `/fa`, `/en`, `/ar` را پشتیبانی کند.

**Spec:** `docs/superpowers/specs/2026-09-28-multilingual-documentation-architecture-design.md`

## Global Constraints

- Plan A (`docs-registry-translation-contract`) باید پیش از implementation merged باشد.
- renderer فقط consumer رجیستری است و حق تغییر status حقوقی/ترجمه را ندارد.
- URLهای canonical زبان واقعی دارند؛ hack `ar` برای فارسی در مرکز اختصاصی ممنوع است.
- missing translation باید status-aware fallback یا پیام روشن بدهد؛ silently زبان اشتباه نشان ندهد.
- RTL برای FA/AR و LTR برای EN بر مبنای locale فعلی اعمال شود.
- language switch باید document identity و تا حد امکان anchor فعلی را حفظ کند.
- search باید زبان انتخاب‌شده را اولویت دهد و نتیجه stale/outdated را با status مناسب نمایش دهد.

## Review Focus

- deep-link مستقیم به یک سند در `/ar/...` وقتی ترجمه ندارد نباید صفحه سفید/404 مبهم بدهد.
- تعویض زبان وسط یک ماده/anchor باید به rendition متناظر همان documentId برود.
- mixed RTL/LTR برای شناسه‌ها و کدها نباید خوانایی را بشکند.
- stale translation باید visually distinguishable باشد.
- cache/service worker نباید پس از تغییر زبان محتوای locale قبلی را برگرداند.

---

### Task 1: Identify the actual docs-center source and deployment owner

**Files:**
- Create in `saeidshojae/EarthCoop`: `docs/documentation/DOCS_CENTER_RUNTIME_AUDIT.md`
- No production code changes.

**Interfaces:**
- Produces a concrete runtime record containing: source repository, default branch, framework/runtime, build command, deploy provider, domain/DNS owner, manifest ingestion path, search implementation, current route scheme, rollback mechanism.

- [ ] **Step 1: Audit current links and deployment evidence**

Start from `config/docs-links.php`, current `docs.earthcoop.ir` deployment metadata, and all accessible repositories/accounts. Record evidence, not assumptions.

- [ ] **Step 2: Locate the source repository or explicitly record it as external/unavailable**

Success condition: one exact repository/path is identified, or the audit states that implementation cannot proceed until the external source is connected/imported.

- [ ] **Step 3: Record current route contract**

At minimum document whether `/#/documents/<id>` is served by the custom center, Mintlify, a proxy, or another frontend, and how it maps to `docs-manifest.json`.

- [ ] **Step 4: Commit audit only**

```bash
git add docs/documentation/DOCS_CENTER_RUNTIME_AUDIT.md
git commit -m "docs: record docs center runtime architecture"
```

### Task 2: Write concrete implementation plan for the discovered center codebase

**Files:**
- Create in `saeidshojae/EarthCoop`: `docs/superpowers/plans/2026-09-28-docs-center-multilingualization-concrete.md`

**Interfaces:**
- Consumes Task 1 runtime audit + manifest v2.
- Produces exact implementation plan with actual source/test paths and commands.

- [ ] **Step 1: Map existing code files by responsibility**

Required units: manifest loader, router, document resolver, language switcher, search index/query, status badge, page shell/direction, caching/service worker if present.

- [ ] **Step 2: Define exact tests before implementation**

Required test behaviors:
- `/fa/<slug>`, `/en/<slug>`, `/ar/<slug>` direct load;
- switch language preserves documentId/anchor;
- no translation shows explicit unavailable/fallback state;
- FA/AR RTL, EN LTR;
- search filters/ranks current language;
- stale/outdated status visible;
- manifest invalidity fails closed with diagnosable error.

- [ ] **Step 3: Save and review concrete plan**

No product code may be modified until the concrete plan has exact file paths and a reviewer/user approval gate.

### Task 3: Implementation gate (intentionally blocked in this umbrella plan)

**Files:** Defined by Task 2 concrete plan.

- [ ] **Step 1: Execute only after Task 2 plan approval**

Use the implementation method selected by the user for the concrete plan.

- [ ] **Step 2: Stage/UAT gate**

Required evidence before Production:
- route contract tests PASS;
- search tests PASS;
- RTL/LTR visual UAT PASS on desktop/mobile;
- direct links from EarthCoop `config/docs-links.php` resolve;
- rollback rehearsal documented.

## Plan Completion Gate

This plan is complete only when the actual docs-center source has been identified, the concrete code-level plan has been separately approved, and staging UAT is green. It deliberately forbids guessing a repository/framework.