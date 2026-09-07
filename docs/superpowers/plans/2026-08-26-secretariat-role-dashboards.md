# Secretariat Role-Aware Operational Dashboards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing Secretariat visibly operable in UAT for the EarthCoop global administrator and group managers/inspectors, including safe office provisioning, actionable dashboard surfaces, office settings, and direct official-record creation using the existing S1–S9 backend.

**Architecture:** Reuse the canonical `SecretariatOffice`, existing policies, record/case/correspondence controllers, and existing registry lifecycle. Add idempotent provisioning helpers for central/group offices and enrich the existing office index into the operational dashboard instead of creating a parallel subsystem. Add a narrow office-settings controller/view guarded by `manage` policy. Inspectors keep read-only/inspection visibility; ordinary members do not gain management permissions.

**Tech Stack:** Laravel 9, Eloquent, Blade/Tailwind, existing EarthCoop Secretariat module, PHPUnit feature tests.

**Spec:** `docs/SECRETARIAT_PRODUCTION_RUNBOOK.fa.md` and the UAT requirement approved on 2026-08-26: central dashboard for global admin, group dashboard for group management, no merge to `main`.

## Global Constraints

- Work only on `agent/najm-hoda-executive-uat-post-elections`.
- Do not merge to `main`.
- Preserve Elections, Najm Hoda, and current integration behavior.
- Reuse existing Secretariat policies; no permission widening by navigation alone.
- Keep provisioning canonical and idempotent.
- Do not bypass existing draft → approval → official registration lifecycle.
- A group inspector may inspect but not obtain group-manager mutation powers.

---

### Task 1: Canonical office provisioning from UAT entry points

**Files:**
- Modify: `app/Modules/Secretariat/Services/SecretariatOfficeService.php`
- Modify: `app/Modules/Secretariat/Controllers/SecretariatDirectoryController.php`
- Test: `tests/Feature/Secretariat/SecretariatDashboardUatTest.php`

**Interfaces:**
- Produces: `ensureCentral(): SecretariatOffice`
- Produces: `ensureGroup(Group $group): SecretariatOffice`

- [ ] Write feature tests proving an admin opening the central shortcut provisions exactly one canonical central office and redirects to it.
- [ ] Write feature tests proving a group role-3 manager opening the group shortcut provisions exactly one canonical group office and redirects to it.
- [ ] Write denial tests proving an ordinary member cannot trigger group-office provisioning and a non-admin cannot trigger central provisioning.
- [ ] Implement transactional/idempotent `ensureCentral()` and `ensureGroup()` using stable codes and existing validation.
- [ ] Update shortcut actions to provision only after authority checks.
- [ ] Run the focused feature tests and confirm zero failures.

### Task 2: Operational dashboard data contract

**Files:**
- Modify: `app/Modules/Secretariat/Controllers/SecretariatController.php`
- Test: `tests/Feature/Secretariat/SecretariatDashboardUatTest.php`

**Interfaces:**
- Produces view data: `counts`, `dashboard`, `recentRecords`, `recentCases`, `canManageOffice`, `canInspectOffice`.

- [ ] Add failing dashboard tests for central admin and group manager/inspector visibility.
- [ ] Add dashboard counts for all visible records, draft, pending approval, registered/formal, correspondence, open cases, and recent activity using policy-filtered data.
- [ ] Provide a limited recent-record and recent-case feed suitable for UAT without changing the registry model.
- [ ] Pass explicit capability booleans to the Blade view.
- [ ] Run focused dashboard tests and confirm zero failures.

### Task 3: Role-aware dashboard UI and direct official-record workflow entry

**Files:**
- Modify: `resources/views/secretariat/index.blade.php`
- Modify: `resources/views/secretariat/create.blade.php`
- Test: `tests/Feature/Secretariat/SecretariatDashboardUatTest.php`

**Interfaces:**
- Consumes existing routes: `secretariat.records.create`, `secretariat.cases.*`, `secretariat.correspondence.*`.

- [ ] Add failing response assertions for dashboard title, quick actions, office identity, operational counters, recent activity, and settings link where authorized.
- [ ] Build a dashboard header that clearly identifies central vs group office and current user capability mode.
- [ ] Add management quick actions: «ثبت سند رسمی», «نامه وارده», «نامه صادره», «مکاتبه داخلی», «پرونده جدید», «تنظیمات دبیرخانه»; hide mutation controls from read-only inspectors/members using policies.
- [ ] Add operational cards for pending approval, drafts, official registry, correspondence, open cases, and total visible records.
- [ ] Add recent records/cases panels and retain the existing advanced search/list below them.
- [ ] Improve the record-create copy so a founder can intentionally choose `policy` for foundational documents and understand that creation starts as a draft before approval/registration.
- [ ] Run focused feature tests and confirm zero failures.

### Task 4: Office settings for central admin and group manager

**Files:**
- Create: `app/Modules/Secretariat/Controllers/SecretariatOfficeSettingsController.php`
- Create: `resources/views/secretariat/settings.blade.php`
- Modify: `routes/secretariat.php`
- Test: `tests/Feature/Secretariat/SecretariatDashboardUatTest.php`

**Interfaces:**
- Produces routes: `secretariat.settings.edit`, `secretariat.settings.update`.

- [ ] Write failing tests that admin/group manager can open settings and inspector/member cannot.
- [ ] Expose only bounded settings already supported by the office model: display name, default confidentiality, registry-number format, sequence width.
- [ ] Validate registry format requires `{SEQ}` and sequence width is 1–12, matching `SecretariatOfficeService` invariants.
- [ ] Persist settings without mutating immutable office type/scope/canonical ownership.
- [ ] Add audit-friendly success feedback and return to the office dashboard.
- [ ] Run focused feature tests and confirm zero failures.

### Task 5: Regression verification

**Files:**
- No production changes unless failures reveal a real regression.

- [ ] Run focused Secretariat dashboard tests.
- [ ] Run the existing Secretariat feature suite.
- [ ] Trigger and inspect EarthCoop Integration Full Validation on the final head.
- [ ] Confirm Full Project PHPUnit and all regression gates are green before declaring the UAT dashboard ready.
