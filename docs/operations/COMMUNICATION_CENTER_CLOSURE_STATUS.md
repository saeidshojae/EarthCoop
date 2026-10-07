# EarthCoop Communication Center — As-Built Closure & Handoff

**Closure date:** 2026-10-06  
**Code baseline:** `main@f316898fa728e87625abab2c18b07aefa750ba62` (PR #216 merged)  
**Status:** Communication Center v1 implementation complete and Production-verified for the active outbound-email scope.

This document is the authoritative **as-built closure record**. The earlier design specification and implementation plan remain historical design/execution documents; where wording differs, this document and the current code/runtime contract describe the implemented state.

## 1. Closure decision

Communication Center v1 is closed for its active Production scope.

The system now has one canonical outbound-email boundary, persisted communication/delivery history, immutable template versions, sender identities, preferences, event/scheduled/conditional automation, campaign controls, priority queues, retry/audit state, authentication email migration, support/contact migration, and an architectural guard preventing new direct email bypasses.

The only active delivery channel in v1 is Email.

## 2. Canonical outbound boundary

Production application code must create a canonical communication through `App\Services\Communication\CommunicationDispatcher`.

Direct Laravel `Mail::*` provider delivery is allowed only in:

`app/Services/Communication/EmailDeliveryAdapter.php`

The architecture guard also rejects:

- Laravel Notification classes that use the `mail` channel;
- `Notification::route('mail', ...)` application delivery;
- new direct `Mail::*` calls outside the adapter.

This is enforced by:

`tests/Architecture/CommunicationDeliveryBoundaryTest.php`

## 3. Implemented capability status

| Area | As-built status |
|---|---|
| Canonical communication/template/sender/preference schema | Complete |
| Immutable localized template versions | Complete |
| Required / operational / optional preference policy | Complete |
| Deterministic deduplication | Complete |
| Critical / normal / bulk communication queues | Complete |
| Retry classification and append-only delivery attempts | Complete |
| Event-driven rules | Complete |
| Scheduled rules | Complete |
| Delayed/conditional rules | Complete |
| Registration welcome automation | Complete |
| Weekly member / manager / inspector reports | Complete |
| Admin dashboard | Complete |
| Delivery history | Complete |
| Failure/retry observability | Complete |
| Template management | Complete |
| Sender identity management | Complete |
| Automation management | Complete |
| Campaign preview / confirm / pause / cancel | Complete |
| User communication preferences / unsubscribe policy | Complete |
| Invitation email migration | Complete |
| Email verification migration | Complete |
| Password reset migration | Complete |
| Support ticket created/reply migration | Complete |
| Public Contact Inbox split/reply | Complete |
| Najm Hoda support escalation email migration | Complete |
| Najm Bahar project-assignment email migration | Complete |
| Architectural direct-email guard | Complete |
| Production scheduler/worker operating contract | Documented and Production-used |
| Inbound provider webhook | Code-ready, **not enabled in Production** |
| Temporal/Time-System integration | **Complete and merged into main** |

## 4. Important canonical purposes/templates

The current implemented set includes, among others:

- `auth.email_verification`
- password-reset canonical communication
- invitation and invitation-rejection communications
- `membership.member_invitation`
- `onboarding.welcome`
- `reports.member.weekly`
- `reports.manager.weekly`
- `reports.inspector.weekly`
- `support.ticket_created`
- `support.ticket_reply`
- `support.ticket_internal_alert`
- `contact.reply`
- `najm_bahar.project_assigned`

Historical design examples may use older names such as `projects.project_assigned`. The implemented Najm Bahar assignment purpose is `najm_bahar.project_assigned`.

## 5. Support and Contact boundary

The final support/contact contract is:

1. `/tickets` is the authenticated member support-ticket system.
2. `/contact` creates a separate `ContactMessage`, not a Ticket.
3. Contact replies are sent through Communication Center.
4. A ContactMessage can be converted to a formal Ticket only when it is linked to a genuinely authenticated user; guest email-address equality is not treated as identity proof.
5. A valid inbound provider-email reply may append to an existing Ticket only when the sender mailbox matches the Ticket mailbox.
6. New/unthreaded inbound email belongs in Contact Inbox, not an automatically-created member Ticket.
7. Email possession alone never grants a webhook comment an EarthCoop member `user_id`.

## 6. Inbound email provider status

The code contains:

`POST /api/email/webhook`

for a Mailgun-compatible inbound-email flow, with signature verification based on `MAILGUN_SECRET`.

**Production state at closure:** Mailgun is not configured and `MAILGUN_SECRET` is not present. Therefore inbound-email webhook processing is intentionally dormant. Without the secret, requests fail signature verification and are rejected.

This is **not a blocker for Communication Center v1 closure**, because the active Production scope is outbound email plus in-app Contact/Ticket handling. Enabling inbound email later is a provider-integration task.

Attachments on inbound-provider email are not a closed Production capability; attachment ingestion remains future work if/when inbound email is enabled.

## 7. Production evidence

The following Production smoke paths were verified during closure:

- scheduled Communication Center delivery reached Gmail through scheduler → queue → worker → provider;
- password reset email delivery succeeded;
- public Contact Inbox submission and admin reply succeeded, including received email;
- member Ticket creation succeeded;
- Ticket display/SLA flow succeeded;
- admin Ticket reply appeared in EarthCoop and arrived by email;
- registration email verification reached the user, the code verified successfully, and registration continued;
- final Ticket-created communication appears in Delivery History as `support.ticket_created` with sent status;
- final Ticket reply appears in Delivery History as `support.ticket_reply` with sent status;
- closure templates `support.ticket_internal_alert` and `najm_bahar.project_assigned` were present in Production after migration.

Najm Bahar project-assignment email did not receive a manual Production smoke because no suitable project-creation path was available at closure time. Its focused tests and the final full-project validation passed, so this is not a closure blocker.

## 8. Final validation and release evidence

Major closure checkpoints included:

- PR #209 — Communication Center scheduling/localization closure;
- PR #210 — Ticket SLA creation hotfix;
- PR #211 — public Contact Inbox split and canonical reply;
- PR #212 — Ticket SLA display hotfix;
- PR #213 — registration verification dependency-injection hotfix;
- PR #214 — verification UX/guest-console hardening;
- PR #216 — final Communication Center delivery-boundary closure.

Final Communication Center closure PR:

- PR: `#216`
- merged commit: `f316898fa728e87625abab2c18b07aefa750ba62`
- Full Validation: `#3942`
- result: **success**

The final closure migration:

`2026_10_06_162000_seed_communication_closure_templates`

was executed in Production and its seeded templates were observed in the admin template list.

## 9. Runtime contract

Production requires:

- scheduler execution every minute for `communications:process-due`;
- queue workers consuming:
  - `communications-critical`
  - `communications-normal`
  - `communications-bulk`
- provider credentials only in environment/secret configuration;
- sender-domain DNS alignment appropriate to the active provider;
- application delivery history/attempt records preserved for audit.

See `docs/operations/COMMUNICATION_CENTER_RUNBOOK.md` for the operational procedure.

## 10. Admin surface

The canonical admin area is `/admin/communications/*`.

Available surfaces include:

- dashboard;
- templates;
- sender identities;
- automations;
- campaigns;
- delivery history;
- failures/retries.

A final sidebar polish to expose Delivery History and Failures/Retry directly in the admin navigation is UI-only and does not change Communication Center runtime architecture.

## 11. Deliberate non-blocking backlog

The following are not reasons to reopen Communication Center v1:

- historical spam-like `TKT...` records created by the old public contact path: preserve or archive non-destructively when desired;
- activation of Mailgun/inbound-email provider integration;
- inbound email attachment download/storage;
- broader provider bounce/complaint suppression integration;
- additional communication channels beyond Email;
- richer infrastructure monitoring beyond application-level queue/scheduler evidence.

Any future work must preserve the canonical CommunicationDispatcher boundary and audit history.

## 12. Temporal / Time-System handoff

Communication Center has now been reviewed and integrated against EarthCoop's unified Time/Temporal architecture.

That integration was completed after the original Communication Center closure via PR #219, with additional repository-wide Temporal hardening in later PRs.

The merged Temporal integration established these requirements:

- preserve this as-built communication architecture as the functional baseline;
- preserve communication deduplication, schedule semantics, audit history, queue behavior and Production-verified flows;
- reconcile `next_run_at`, scheduled-rule evaluation, queue timestamps, retry timing, display timezone and other temporal fields with the central Time System;
- avoid changing communication business meaning merely to normalize time representation;
- add targeted regression coverage before full-project validation.

For time-related behavior, the merged Temporal documentation and current `main` are authoritative.

## 13. Historical-document rule

The following files remain valuable historical sources:

- `docs/superpowers/specs/2026-09-30-earthcoop-communication-center-design.md`
- `docs/superpowers/plans/2026-09-30-earthcoop-communication-center.md`
- self-review and approval-note documents from the design phase.

They must not be read as live completion trackers. This closure document is the source of truth for **what was actually built and closed on 2026-10-06, with Temporal integration status updated on 2026-10-07**.
