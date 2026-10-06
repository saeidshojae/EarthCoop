# EarthCoop Email Content v2 — Implementation Readiness

**Audit date:** 2026-10-06  
**Baseline inspected:** `main@f316898fa728e87625abab2c18b07aefa750ba62`  
**Purpose:** Freeze concrete route/logo dependencies and concurrency boundaries before runtime implementation.

## 1. Canonical brand asset

Current header logo:

- repository asset: `public/images/logo.png`
- Blade usage: `asset('images/logo.png')`
- current header also displays the text `EarthCoop`.

**Implementation decision:** professional email layout should reuse this asset using an absolute `asset('images/logo.png')` URL and retain visible text `EarthCoop`. Website-only animation/filter styling must not be copied into email.

## 2. Confirmed member-facing route inventory

The following route names already exist and are safe candidates for context-generated email URLs:

| Purpose | Route name | Access |
|---|---|---|
| Member home/dashboard | `home` | authenticated |
| Profile | `profile.show` | authenticated |
| My groups | `groups.index` | currently registered as `/groups`; member UI uses it |
| My participation | `history.index` | authenticated |
| My location & governance | `location-governance.me` | authenticated |
| Communication preferences | `profile.communication-preferences` | authenticated |
| Ticket list | `user.tickets.index` | authenticated |
| Ticket detail | `user.tickets.show` | authenticated |
| Registration form | `register.form` | public registration flow |
| Najm Bahar agreement | `najm-bahar.agreement` | authenticated |
| Najm Bahar project detail (member) | `najm-bahar.projects.show` | authenticated |
| Najm Bahar project detail (admin/reviewer current assignment flow) | `admin.najm-bahar.projects.show` | admin |

## 3. Getting-started/docs link status

A generic dynamic page system exists under `PageController`, including a `help` template, but the audit did not establish one guaranteed canonical named route/slug that should serve as the long-term “EarthCoop چیست؟ / شروع کار” destination.

Therefore **do not hard-code a help/docs URL into Welcome v2 yet**.

Implementation choices, in preference order:

1. if the public Docs Center has a stable canonical URL/route in the repository at implementation time, use it;
2. otherwise add/choose an explicit stable “getting started” route;
3. as a temporary fallback, omit the secondary docs link rather than hard-code an uncertain URL.

## 4. Welcome v2 URL contract

Recommended context keys:

- `home_url` → `route('home')`
- `profile_url` → `route('profile.show')`
- `groups_url` → `route('groups.index')`
- `participation_url` → `route('history.index')`
- `governance_url` → `route('location-governance.me')`
- `communication_preferences_url` → `route('profile.communication-preferences')`
- `getting_started_url` → unresolved until canonical destination is confirmed

Najm Bahar should not be made a mandatory Welcome step unless product onboarding explicitly wants it and the member path is stable for every new user.

## 5. Invitation URL contract

For requested/member invitation templates:

- `registration_url` → `route('register.form')`
- `learn_more_url` → same unresolved canonical getting-started destination

Dates/expiry values should be formatted through the unified Temporal contract after that work lands; do not freeze ISO timestamps into human-facing v2 copy.

## 6. Report URL contract

Member weekly report:

- `dashboard_url` → `route('home')`
- `groups_url` → `route('groups.index')`
- `participation_url` → `route('history.index')`
- `preferences_url` → `route('profile.communication-preferences')`

Manager/inspector role-specific destination remains to be resolved from the final role responsibility UI. Until a stable route exists, the primary CTA may safely point to `history.index` or `home` rather than inventing a management/inspection route.

## 7. Support URL contract

Ticket-created/reply:

- ticket URL → `route('user.tickets.show', $ticket)`
- tickets index → `route('user.tickets.index')`

Current Production has no active inbound Mailgun provider, so email copy must instruct the member to use the ticket page for replies.

## 8. Najm Bahar project-assignment URL contract

Current implementation sends reviewers to:

`route('admin.najm-bahar.projects.show', $project)`

This is suitable only for recipients who are actually authorized for that admin/reviewer surface. Content work must not substitute `najm-bahar.projects.show` without confirming the recipient role and workflow.

## 9. Concurrency with unified Time/Temporal work

A separate Time/Temporal workstream is active and has already reached Communication Center checkpoints.

Do not concurrently implement changes to likely overlapping time-sensitive files such as:

- communication scheduling services;
- scheduled-rule recurrence/timezone semantics;
- weekly report period calculation/display;
- template renderer/model/schema if the Temporal branch changes recipient temporal context;
- invitation/report human date formatting before the central temporal formatter contract is available.

Safe/non-overlapping implementation candidates can be prepared separately:

- shared email Blade layout/components;
- logo/header/footer rendering;
- Ticket Created/Reply Blade copy/layout;
- content-only tests around unsupported reply-by-email wording;
- route inventory tests;
- static content/style documentation.

Before any runtime v2 branch is merged, rebase/rebuild from the latest `main` after the relevant Temporal checkpoint and compare overlap.

## 10. Pre-merge implementation gates

The eventual runtime content v2 PR should not merge until:

1. it is based on latest `main` after the relevant Temporal merge/checkpoint;
2. no stale Temporal semantics are reintroduced;
3. all new links are named-route-generated;
4. published template history is preserved by new versions;
5. logo resolves through the canonical EarthCoop asset;
6. ticket reply copy matches actual inbound-provider state;
7. focused Communication Center content/render tests pass;
8. authentication-sensitive mail regressions pass;
9. weekly report tests pass if those templates change;
10. full project validation passes;
11. controlled preview/smoke confirms Gmail/mobile readability.

## 11. Current readiness decision

The **content direction is implementation-ready**.

The **full runtime implementation is intentionally not merged yet** because the parallel Temporal work owns or may alter some of the same semantics/files. Safe visual/support-copy work can proceed independently, while Welcome/report/invitation date-sensitive context changes should wait for the latest Temporal baseline.
