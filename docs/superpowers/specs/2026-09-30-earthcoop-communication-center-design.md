# EarthCoop Communication Center — Design Specification

> **Document role after implementation:** This is the approved historical design baseline. Use `docs/operations/COMMUNICATION_CENTER_CLOSURE_STATUS.md` and current `main` for authoritative as-built behavior. Later Time/Temporal integration is already merged and may supersede early scheduling examples. The implemented Najm Bahar assignment purpose is `najm_bahar.project_assigned`, not the earlier illustrative `projects.project_assigned`.


**Date:** 2026-09-30
**Implementation reconciliation:** 2026-10-07

## 1. Purpose

EarthCoop needs one programmable communications subsystem that can reliably deliver official and user-facing communications based on business events, schedules, conditions, roles, audiences, and user preferences. Email is the first delivery channel, but the domain is designed as a Communication Center so that future channels can reuse the same policy, rule, audience, scheduling, audit, and consent foundations.

The target system must support all three automation modes:

1. **Event-driven** — e.g. when a member completes all three registration stages, send a welcome / thank-you / getting-started email automatically.
2. **Scheduled / recurring** — e.g. weekly role-aware reports for members, managers, and inspectors.
3. **Conditional / delayed** — e.g. after a future delay, re-check a safe registered condition and send a reminder only when the condition still holds.

The system must also support one-off and bulk admin campaigns, test sends, pause/cancel for pending bulk work, delivery history, retries, failure inspection, user communication preferences, and future controlled use by Najm Hoda.

## 2. Current-state findings and design response

EarthCoop already has useful email infrastructure, but it is fragmented across several paths:

- `EmailTemplate` and its admin CRUD;
- `SystemEmail` and its admin CRUD;
- `EmailDeliveryService` for admin HTML delivery;
- direct `Mail::to()`, `Mail::send()`, `Mail::raw()`, and `Mail::html()` usage in multiple domains;
- Laravel `Notification` classes, including queued notifications;
- dedicated Mailables such as invitation and FAQ mail;
- `SystemIdentityService` with system actor identities such as support and management;
- an email↔ticket integration that already handles organizational sender identity, reply-to, message IDs, and reply threading.

This design does **not** replace those paths in one disruptive rewrite. It uses a strangler migration: build the canonical communication core, migrate existing flows incrementally, keep regression coverage around mature flows, and remove direct legacy delivery only after each migrated path is proven.

A critical clarification from the design review is that `SystemIdentityService` and email sender identities are related but not identical concepts. A system identity may represent an in-app actor with user semantics. The Communication Center therefore does not delete that concept. Instead, sender identities may reference or resolve from a registered system identity where appropriate while remaining a separate communication concern.

## 3. Core architecture

The canonical flow is:

```text
Business Event / Schedule / Admin Action
                │
                ▼
        Communication Engine
                │
      ┌─────────┼─────────┐
      │         │         │
    Rule      Audience   Template
      │         │         │
      └─────────┼─────────┘
                ▼
      Classification / Preference
                │
                ▼
          Sender Identity
                │
                ▼
        Communication Record
                │
                ▼
          Priority Queue
                │
                ▼
        Delivery Adapter
                │
                ▼
       Attempt / Retry / Audit
```

Domain services emit business events and provide safe domain context. They do not contain presentation copy, recipient-selection SQL, or SMTP behavior.

The Communication Center owns:

- communication rules;
- registered trigger and condition interpretation;
- audience resolution;
- communication classification and preference decisions;
- templates and template versions;
- sender identity selection;
- scheduling and delayed condition checks;
- queue dispatch and priority;
- delivery attempts and retry behavior;
- deduplication/idempotency;
- campaigns and runs;
- audit and operator observability.

## 4. Explicit boundaries

### 4.1 In scope for v1

- Email as the only active channel.
- Template registry and immutable published template versions.
- Persian first, with locale-aware schema ready for English and Arabic.
- Sender identities.
- Event-based rules.
- Scheduled / recurring rules.
- Delayed conditional checks using registered safe conditions.
- Base audience resolvers.
- Required / operational / optional communication classification.
- Base user email preferences.
- Priority queues.
- Retry and permanent failure states.
- Deduplication.
- Delivery logs and attempts.
- Admin dashboard and automation management.
- Manual and scheduled campaigns with preview, confirmation, pause, and cancel of pending work.
- Automatic welcome communication after successful completion of registration stage 3.
- Weekly role-aware reports for member / manager / inspector.
- Incremental migration of major existing email paths.

### 4.2 Out of scope for v1

- Running an SMTP server.
- Arbitrary SQL/PHP/Blade execution from admin rules.
- General-purpose workflow automation.
- Full Mailchimp-style marketing automation.
- Complex drag-and-drop email designer.
- SMS, WhatsApp, or full push delivery.
- Autonomous AI sending.
- Advanced compound segmentation/query builders.
- Full marketing open/click analytics.
- Recipient-local-time execution as a required v1 capability.

The schema may reserve future extension points, but v1 implementation must not build these features prematurely.

## 5. Communication classifications and user choice

Three classifications are canonical:

### Required transactional

Necessary for account access, security, identity verification, critical official obligations, or other service-critical communication. User opt-out does not suppress these messages.

Examples:

- email verification;
- password/security events;
- critical account notices;
- official responsibility notices where delivery is required for the workflow.

### Operational

Useful service or role communications that are enabled by default but can be adjusted or disabled by the user where policy permits.

Examples:

- weekly manager report;
- weekly inspector report;
- project status summaries;
- role reminders;
- onboarding welcome / getting-started guidance.

The welcome email after registration completion is **operational and default-on**. It must be sent automatically for a newly completed registration unless an applicable preference already exists that explicitly suppresses operational onboarding communication.

### Optional

News, discovery, general announcements, promotional or non-essential digests. These honor user preferences and may require explicit opt-in depending on jurisdiction/policy.

Optional email must provide a direct preferences/unsubscribe path without affecting required transactional delivery.

## 6. Trigger model

### 6.1 Event trigger

A registered business event starts evaluation immediately.

Example:

```text
registration.completed
        ↓
onboarding.welcome rule
        ↓
event.user
        ↓
queue
```

The registration flow publishes the event only after all three registration stages have completed successfully. It does not directly construct or send the welcome email.

### 6.2 Scheduled trigger

A recurring schedule creates a `CommunicationRun`, resolves its audience, applies preferences, builds per-recipient context, and queues eligible recipients.

Example:

```text
Every Monday at 08:00 in the schedule's explicit IANA timezone
Audience: active managers
Template: reports.manager.weekly
```

Implemented scheduling contract (merged via PR #219):
- scheduler clocks evaluate due work in canonical UTC;
- each schedule has an explicit timezone semantic rather than relying on the worker/process timezone;
- recurrence advances from the **planned occurrence**, not from the actual worker execution time, preventing drift after delayed jobs;
- each `CommunicationRun` persists its planned occurrence in `scheduled_for`;
- DST/timezone transitions are covered by recurrence tests;
- weekly report calendar-day periods are derived from the planned occurrence in the schedule timezone, then converted to canonical query instants.

The scheduler orchestrates work; it never performs SMTP delivery directly.

### 6.3 Conditional / delayed trigger

Prefer event-relative delayed checks over repeated broad scans.

Example:

```text
registration.completed
        ↓
schedule check +3 days
        ↓
registered condition evaluator
        ↓
condition still true? → send
condition false?      → stop
```

Broad state scans are allowed only for explicitly registered, bounded use cases with known query cost.

## 7. Registries: safe programmability, not arbitrary code

### Event Registry

Every automatable event has a stable key and payload contract. Initial examples include:

- `registration.completed`
- `security.password_changed`
- `governance.election_started`
- `governance.manager_selected`
- `governance.inspector_selected`
- `location.proposal_approved`
- `support.ticket_created`
- `projects.project_assigned`
- `projects.status_changed`

The actual v1 registry is limited to events proven by migrated use cases. No speculative event is required merely because it appears in this design.

### Condition Registry

Conditions are code-defined, testable evaluators with explicit inputs and cost characteristics. Example categories:

- registration state;
- email verification state;
- last-login age;
- role state;
- group membership;
- election state;
- project state.

Admin users select registered conditions and values. They cannot submit SQL, PHP, or free executable expressions.

### Audience Registry

Initial audience resolvers include:

- event user;
- specific user;
- group members;
- active members of a registered responsibility/role;
- selected location membership only where an existing canonical location membership query is available.

Large audiences are resolved in chunks and never loaded into a single admin dropdown.

## 8. Templates and rendering

### CommunicationTemplate

Stable identity of a communication purpose:

- `key`
- `name`
- `category`
- `classification`
- `active`

Examples:

- `onboarding.welcome`
- `reports.member.weekly`
- `reports.manager.weekly`
- `reports.inspector.weekly`
- `support.ticket_received`

### CommunicationTemplateVersion

Published content is immutable. Editing a published template creates a new version.

Fields conceptually include:

- template ID;
- version number;
- locale;
- subject;
- body;
- declared variables schema;
- sender identity key/reference;
- publish timestamp;
- author/approver audit fields where applicable.

Each recipient record points to the exact published template version used for rendering.

Template variables are registered and validated. Unknown required variables are a render failure, not silently left as unresolved placeholders.

Templates cannot execute arbitrary application code or query the database. Dynamic data is supplied by explicit context builders.

HTML rendering uses a controlled allowed-content policy suitable for admin-authored email. Templates must not become an arbitrary script execution surface.

## 9. Report context builders

Role-aware reports use dedicated context builders, for example:

- `WeeklyMemberReportContextBuilder`
- `WeeklyManagerReportContextBuilder`
- `WeeklyInspectorReportContextBuilder`

Builders transform domain data into safe structured context. Templates only render those values.

A weekly report run may share the same schedule but must build context per recipient, because role, responsibilities, outstanding work, and counts are user-specific.

The report context snapshot is retained with the generated communication sufficiently to explain what the system knew at generation time without storing unnecessary sensitive domain data.

## 10. Sender identity model

`CommunicationSenderIdentity` owns email presentation identity:

- stable key;
- email address;
- display name;
- reply-to;
- active state;
- default state where needed;
- purpose/description;
- optional link/resolver key to an existing `SystemIdentityService` identity.

Initial likely keys include:

- `system`
- `support`
- `management`
- `secretariat`
- `governance`
- `security`
- `projects`
- `najm_bahar`

Only verified/configured domains may be used as sender addresses.

SMTP/provider credentials and API secrets remain in environment/secret configuration, not in database sender records or ordinary admin forms.

The existing `SystemEmail` records are migration input; they are not treated as a second permanent source of truth after migration.

## 11. Operational data model

The exact migration syntax belongs in the implementation plan, but the domain model is fixed as follows.

### `communication_templates`

Stable purpose metadata.

### `communication_template_versions`

Immutable published localized content and variable schema.

### `communication_sender_identities`

Canonical outbound email identities.

### `communication_rules`

Declarative event / scheduled / conditional rule definitions, including classification, priority, audience definition, condition definition, delay/schedule reference, active state, and audit ownership.

### `communication_rule_schedules`

Structured recurrence configuration with explicit schedule timezone semantics. Runtime recurrence is anchored to the planned occurrence, while due evaluation is canonical UTC.

### `communication_runs` planned occurrence

Each scheduled run persists `scheduled_for` as the canonical planned occurrence. Recipient/report context builders use this value (with legacy fallback only where necessary) rather than treating worker start time as the schedule definition.

### `communication_campaigns`

One-off or scheduled admin-initiated campaign lifecycle.

### `communication_runs`

One execution of a scheduled rule or campaign, with aggregate counts and status.

### `communications`

Logical communication generated from an event, rule, campaign, or manual action. It records source linkage, chosen template version, classification, priority, context snapshot, deduplication key, scheduled time, and lifecycle status.

### `communication_recipients`

Per-recipient destination, locale, preference decision, lifecycle state, and timestamps.

### `communication_delivery_attempts`

Append-only attempt history with provider identity/message ID when available, timestamps, result, and normalized failure classification.

### `communication_preferences`

User/topic/channel preference and cadence. Required transactional communications bypass suppressive preferences.

### `communication_digest_items`

Reserved only if simple digest support is implemented in a later release. It is not required for initial v1 completion and should not be migrated until a digest use case is approved.

## 12. Idempotency and duplicate prevention

Every automatable communication purpose defines a deterministic deduplication key.

Examples:

```text
onboarding.welcome:user_122:registration_completion_1
manager_selected:election_845:user_122
reports.manager.weekly:user_122:2026-W40
```

The storage layer must enforce duplicate prevention for the intended uniqueness scope, not merely perform a best-effort application check.

Queue retry, worker crash, scheduler re-entry, or duplicate event delivery must not create duplicate logical communications.

## 13. Queue and priority

All normal outbound email is queued. The request that causes a business event must not wait for SMTP completion.

Canonical queue classes/priorities:

- critical — verification, password/security, other required access-critical mail;
- normal — operational communication and individual notifications;
- bulk — campaigns and large reports.

The implementation may map these priorities onto the repository's existing queue driver/configuration rather than introducing a new external queue product.

Large recipient sets are chunked and rate-controlled. A bulk campaign must not starve critical account email.

## 14. Retry and failure handling

Delivery failures are normalized as transient or permanent where determinable.

Transient examples:

- timeout;
- temporary provider outage;
- connection reset;
- provider rate limit.

Permanent examples:

- invalid destination known before delivery;
- hard failure explicitly returned by an integrated provider when available.

Retry uses bounded backoff. A failed attempt is never overwritten; the next attempt is appended.

Exhausted transient retries and permanent failures become operator-visible failed recipients.

Provider-specific bounce/complaint webhooks are adapter capabilities and are not required for a basic SMTP-only first release. The model must allow later suppression signals without redesigning the communication domain.

## 15. Campaigns and admin safety

Campaign lifecycle:

```text
Draft
→ Audience preview
→ Sample/test render
→ Recipient estimate
→ Confirmation
→ Scheduled / Running
→ Completed / Paused / Cancelled / Failed
```

Pause/cancel affects pending work only; already delivered email cannot be recalled.

Large campaigns require a configurable elevated confirmation threshold. The schema and permission model permit later separation of creator and approver for very large or sensitive sends, but dual approval is not mandatory in the first implementation unless an existing project policy already requires it.

Admin rules and campaigns never accept arbitrary executable expressions.

## 16. User preferences and unsubscribe

Users receive a communication-preferences UI covering operational and optional email topics/cadence.

Required transactional communication is clearly shown as non-disableable where policy requires it.

Optional communication must include a direct preference/unsubscribe path. Unsubscribe changes optional/eligible preferences only and must never disable account-security or other required transactional communication.

Operational report preferences must support at least on/off in v1; cadence choices are supported where a corresponding registered schedule exists.

## 17. Admin Communication Center

The existing email-management area evolves into **Communication Center** with these sections:

1. Dashboard
2. Templates
3. Sender identities
4. Automations
5. Campaigns
6. Automatic reports
7. Delivery history
8. Failures / retries
9. Settings / preference policy

### Dashboard

Shows at minimum:

- queued;
- sent;
- retrying;
- permanently failed;
- upcoming scheduled runs;
- active automations;
- queue health indicators available from the current infrastructure.

### Template editor

Supports:

- localized version editing;
- registered-variable display;
- sample preview;
- test send;
- publish as a new immutable version.

### Automation builder

Uses controlled select inputs for:

- registered trigger;
- registered conditions;
- registered audience;
- template;
- sender;
- classification;
- delay/schedule;
- active state.

It is not a generic scripting environment.

### Delivery history

Filters by date, status, template, sender, recipient, campaign/run, and automation where available.

### Failure view

Shows normalized failure reason, attempts, and safe retry actions.

## 18. First reference automation: registration welcome

Definition of done:

1. User completes registration stages 1, 2, and 3 successfully.
2. Canonical `registration.completed` is emitted exactly for the completion transition.
3. `onboarding.welcome` rule matches the event user.
4. Operational/default-on preference policy is evaluated.
5. Per-user context is built.
6. A deterministic dedupe key is persisted.
7. Delivery is queued.
8. Email is sent asynchronously.
9. Recipient status and attempt history are visible to admin.
10. Replaying the event/job does not send a second welcome email.

Initial welcome context may include only values that are already authoritative and inexpensive to resolve, such as first name, dashboard/start URL, and current group count. Additional onboarding metrics are added only when backed by an approved context builder.

## 19. Second reference automation: weekly role-aware reports

The first scheduled reference flow produces weekly reports for:

- ordinary member;
- manager;
- inspector.

A recipient with a role receives the corresponding role-aware report context. If a user can legitimately hold only one active responsibility under EarthCoop's governance rules, report role selection follows that canonical responsibility state rather than inventing a separate communication-role hierarchy.

Each report period has deterministic deduplication per user/report type/period.

Weekly reports are operational and user-configurable.

## 20. Migration strategy

### Wave 0 — Inventory and characterization

Before changing delivery behavior:

- inventory all direct mail, notifications, Mailables, views, sender identity sources, queue behavior, and relevant tests;
- add characterization tests around mature critical flows that lack coverage;
- record the current default mailer/from behavior.

### Wave 1 — Low-risk foundation

- canonical sender identities;
- template/version registry;
- communication records;
- delivery attempts;
- admin test send through the new service;
- new registration welcome automation.

### Wave 2 — Operational flows

Migrate lower-risk existing flows first, such as selected FAQ/support/project notifications, while preserving ticket reply threading and Message-ID behavior.

### Wave 3 — Authentication and invitation flows

Migrate invitation, verification, and password/account-access email only after the communication core, queue, dedupe, retry, and critical-priority behavior are proven by regression tests.

### Final migration gate

After all approved mail flows migrate, introduce an architectural guard that prevents new direct `Mail::*` delivery outside explicit communication infrastructure/adapters and documented framework exceptions.

## 21. Compatibility API

A central service provides the stable application-facing interface. Exact PHP signatures are an implementation-plan decision after repository-level interface review, but the capabilities are fixed:

- dispatch from a registered business event;
- send a registered template to an explicit recipient/audience;
- schedule or execute a registered rule;
- create/test/confirm a campaign;
- retry eligible failed recipient delivery.

Existing domains migrate to this API incrementally.

## 22. Security and authorization invariants

- No SMTP/API secret is stored in communication database tables.
- No arbitrary PHP/SQL/template code is executable from admin rules.
- Sender addresses are restricted to configured/verified identities.
- Bulk send, template management, rule management, sender management, and retry permissions are distinct.
- Every campaign/rule/template publication has an attributable actor.
- Published template versions are immutable.
- User-supplied values inserted into templates are escaped/normalized according to their variable type and rendering policy.
- Sensitive domain snapshots store only data needed to explain or reproduce the communication, not unrestricted copies of source records.
- CSRF/auth/authorization follow existing admin application conventions.

Suggested permission families:

- `communications.view`
- `communications.templates.manage`
- `communications.rules.manage`
- `communications.campaigns.create`
- `communications.campaigns.approve`
- `communications.delivery.retry`
- `communications.sender_identities.manage`

The implementation plan must reconcile these names with the repository's existing permission registry before creating them.

## 23. Deliverability and infrastructure requirements

Production email requires application behavior plus infrastructure configuration:

- SPF;
- DKIM;
- DMARC;
- valid sender domain alignment;
- production queue worker;
- scheduler/cron health;
- configured SMTP/provider limits.

These are deployment requirements, not database secrets.

The Communication Center dashboard should surface application-level evidence that worker/scheduler/queue delivery is unhealthy where that evidence is available, but v1 does not need to become a general infrastructure monitoring platform.

## 24. Observability and audit

Minimum operational metrics/state:

- queued recipient count;
- sent count;
- retrying count;
- permanently failed count;
- queue latency where measurable;
- delivery latency where measurable;
- failure counts by normalized class;
- failures by template/sender/provider where available.

Audit must answer:

- who created/changed/published a template;
- who created/changed/activated a rule;
- who created/confirmed/paused/cancelled a campaign;
- what template version, sender identity, context snapshot, classification, and preference decision were used for a recipient;
- what happened on each delivery attempt.

## 25. Testing constitution

The implementation requires targeted tests around at least these behaviors:

- rule matching;
- preference enforcement;
- required transactional bypass of opt-out;
- operational/default-on behavior;
- template variable validation;
- template version immutability;
- sender identity resolution;
- deterministic deduplication;
- duplicate event/job replay;
- queue selection/priority;
- transient retry;
- permanent failure;
- campaign audience preview;
- campaign pause/cancel of pending work;
- scheduled rule idempotency;
- conditional delayed re-check;
- welcome email end-to-end;
- weekly member report;
- weekly manager report;
- weekly inspector report;
- compatibility/regression for each migrated legacy path.

Failure-injection tests should include:

- SMTP/provider unavailable;
- worker/job retry;
- duplicate scheduler trigger;
- invalid destination;
- inactive sender;
- missing required template variable;
- recipient preference suppression;
- campaign paused during processing;
- campaign cancelled during processing.

Full-project validation remains a final gate; development should use focused targeted tests first to avoid unnecessary long CI cycles.

## 26. Release sequence

### Release A — Foundation

Canonical domain tables, sender identities, template/version registry, communication/recipient/attempt records.

### Release B — Reliable delivery

Queue, priority, retries, deduplication, normalized failures, delivery observability.

### Release C — Automation

Event, scheduled, and delayed conditional rule execution with safe registries.

### Release D — Admin Center

Dashboard, templates, sender identities, automation builder, logs/failures.

### Release E — Preferences

Required/operational/optional policy and user preference UI.

### Release F — Reference automations

Registration welcome and weekly member/manager/inspector reports.

### Release G — Legacy migration

Incrementally migrate invitations, verification/account mail, support/FAQ/project flows, and other inventoried direct sends.

### Release H — Campaigns

Audience preview, test send, scheduling, confirmation, chunking, pause/cancel, and run reporting.

Release sequencing may be implemented in fewer pull requests if dependency order and independent verification gates are preserved.

## 27. Najm Hoda integration boundary

Najm Hoda may later:

- suggest communication copy;
- prepare a template draft;
- prepare a campaign draft;
- recommend an audience/rule from registered safe options;
- summarize communication health for the founder/admin.

Najm Hoda does not bypass Communication Center policy, permissions, preference checks, approval gates, or delivery audit. AI-produced content remains draft until the applicable human/system policy authorizes publication or sending.

## 28. Final definition of done

Communication Center v1 is complete when the following can happen without application-code editing for normal operation:

1. An admin can manage sender identities and published template versions safely.
2. An admin can configure an approved event/scheduled/conditional automation using registered options.
3. Completing registration stage 3 automatically produces exactly one queued welcome email and a visible audit trail.
4. Weekly role-aware reports are generated and sent according to schedule and user preference.
5. Temporary provider failure retries safely without duplicate logical communication.
6. Permanent failures are visible and actionable.
7. Admin can create, preview, test, schedule, pause, or cancel pending portions of a campaign.
8. Required transactional mail cannot be accidentally disabled by an optional-email unsubscribe.
9. Major legacy mail paths have been migrated or explicitly documented as temporary exceptions.
10. All targeted communication tests and the final project validation gate pass.

## 29. Design decision

EarthCoop will implement **Communication Center as a dedicated communication subsystem, with Email as its first channel**.

Domain modules remain authorities for business state. They emit stable events and supply safe context. Communication Center is the authority for communication rules, audience resolution, preference policy, templates, sender identity selection, scheduling, queueing, delivery, retries, deduplication, and communication audit.

This provides a practical system for today's EarthCoop operations while creating a stable future boundary for additional channels and Najm Hoda assistance without making AI, SMTP, or admin-authored rules a source of business authority.


---

## 30. Temporal integration status — 2026-10-07

Communication Center's Temporal integration is implemented and merged into `main` via PR #219.

The merged contract includes:
- campaign date/time input through the central Temporal input/parser path rather than raw `datetime-local`;
- admin communication timestamps through shared Temporal Blade components;
- explicit schedule timezone semantics;
- canonical UTC scheduler clocks;
- planned-occurrence recurrence and persisted `CommunicationRun::scheduled_for`;
- recurrence behavior that does not drift based on late worker execution;
- DST/timezone regression coverage;
- scheduled campaign activation command + Laravel scheduler wiring;
- weekly-report periods based on the planned occurrence in the schedule timezone;
- recipient-context Temporal rendering of report period dates;
- Communication-specific Temporal architecture regression tests.

Validation on the merged implementation:
- Temporal System Targeted Gate #569 — success
- Responsive Contract Validation #1024 — success
- Integration Full Validation #3948 — success

This section supersedes older wording that could be read as process/system-timezone scheduling semantics.
