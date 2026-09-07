# Founder Ministry Phase 4 — Executive Agency Design

## Purpose

Turn the Founder Ministry from an executive reporter into an honest executive minister: every brief must distinguish what Najm Hoda can see, what it can prepare, what it can execute under an active delegation, what requires the founder's explicit decision, and what remains blocked by policy or missing canonical connectivity.

## Safety and integration constraints

- Work only on `agent/najm-hoda-executive-uat-post-elections`.
- Do not merge to `main`.
- Preserve the integrated elections ancestry and all post-elections integration work.
- Reuse the existing Founder Ops authority, delegation, approval, connectivity, work-queue, and canonical domain services. Do not create a parallel execution path.
- Typed free text in the ministry remains non-executable. Sensitive actions remain available only through explicit cards and the existing Founder Ops lifecycle.
- Unknown actions remain fail-closed.
- A policy entry is not evidence of executable capability. Connectivity evidence remains authoritative for whether a mapped action is actually connected.
- `secretariat.dispatch_formal_record` remains blocked until real transport exists.

## Current verified baseline

Phase 3 already provides a current-report panel, a separate conversation surface, domain-scoped executive conclusions, exception-driven morning briefs, and a distinction between urgency and pending founder decisions. Full Validation #1178 is green on commit `779658363a7febe84b39a9ebf20480c2d1ea6c98`.

The existing authority matrix already models five modes: `observe`, `propose`, `approval_required`, `delegated_safe`, and `forbidden`. The delegation service records explicit expiring grants. The connectivity service distinguishes connected, missing, protected, and blocked-dependency actions. The work queue already exposes approvals, prepared proposals, and attention-only items.

## Phase 4 contract: Ministerial Agency

Each ministry response gains a structured `management.executive.agency` object. It is descriptive and evidence-backed; it does not execute anything.

The object contains:

- `scope`: `global` or `intent`.
- `domain_keys`: policy domains represented by the current ministry intent.
- `connected`: whether canonical read/connectivity evidence exists for the represented domains.
- `active_delegations`: active grants relevant to the represented domains.
- `may_do_now`: connected actions that are already executable under an active explicit delegation. This must never infer permission from `delegated_safe` alone.
- `may_prepare`: connected `propose` or `delegated_safe` actions that Najm Hoda can prepare/analyse without claiming that a mutation was executed.
- `needs_founder_decision`: connected `approval_required` actions represented by current pending approval cards.
- `blocked`: actions that are forbidden, missing an adapter, or have a declared blocked dependency. Blocked items carry a machine-readable reason and must never be presented as available work.
- `summary`: a concise Persian statement of ministerial agency for the current report.

For global morning/end-of-day briefs, agency is derived primarily from the current work-queue items and active delegations rather than dumping all 84 policy actions. For domain briefs, agency is derived from the intent's mapped policy domains plus the current items.

## Intent-to-domain mapping

The ministry uses one canonical mapping so that reporting, agency, and UI semantics agree:

- `users_registration` -> `users`, `invitations`
- `reference_data` -> `reference_data`, `locations`
- `support_moderation` -> `support`, `reports_moderation`
- `groups` -> `groups`
- `governance` -> `governance`
- `najm_bahar` -> `najm_bahar`
- `stock` -> `stock`
- `secretariat` -> `secretariat`
- `communications` -> `email`, `blog`, `content`, `notifications`, `support`
- `system_health` -> `runtime_health`
- `authority` -> all policy domains, but the UI summarizes counts rather than listing every action by default.
- `urgent_items`, `pending_approvals`, `morning_brief`, `end_of_day` -> domains represented by the current report items.

## Presentation model

The executive report keeps the conclusion short. Raw metrics remain in the existing “شاخص‌های همین گزارش” cards instead of being repeated as a long database-like sentence.

The report body gains a compact “توان اجرایی نجم هدا در این گزارش” section with four lanes:

1. **خودم می‌توانم انجام دهم** — only actions with canonical connectivity and an active delegation that makes execution currently legal.
2. **می‌توانم آماده کنم** — analysis/draft/proposal work that can be prepared without founder approval; this is not a claim that the work has already been performed.
3. **تصمیم شما لازم است** — current pending approval cards/actions only, not every approval-required action in policy.
4. **فعلاً مسدود است** — relevant missing/blocked/forbidden capability, shown only when it matters to the current scope.

When a lane is empty, the UI says so plainly rather than implying a capability. For a quiet domain, the executive message becomes a short conclusion (for example, “گروه‌ها در وضعیت فعلی نیازمند اقدام شما نیست.”); the metric cards below carry totals such as 81 groups.

## No false claims

Phase 4 must preserve the distinction among:

- **can execute now**: connected + policy permits execution + explicit delegation/approval lifecycle condition is satisfied;
- **can prepare**: connected preparation/analysis capability exists, but no mutation is claimed;
- **awaiting founder**: a real current approval request exists;
- **defined in policy**: only a declared contract, not proof of connectivity or permission;
- **blocked**: missing canonical dependency/adapter or forbidden by policy.

A `delegated_safe` action with no active grant must not appear under “خودم می‌توانم انجام دهم”. It may appear under preparation/available-safe-capability with wording that does not claim execution authority.

## Implementation boundaries

Create a focused `FounderMinistryAgencyService` responsible only for translating authority + delegation + connectivity + current report items into the agency object. Do not enlarge `FounderMinistryExecutivePresenter` into an authority engine.

`FounderMinistryChatService` supplies canonical intent/domain context and current work items. `FounderMinistryExecutivePresenter` consumes the agency object to produce concise executive copy. `chat.blade.php` renders the four agency lanes; it does not calculate authority in JavaScript.

The existing `FounderActionExecutionService` remains the sole execution gate for domain mutations. Phase 4 does not call it from report rendering.

## Tests and acceptance

TDD coverage must prove at least these cases:

1. A `delegated_safe` action without a grant is not reported as executable now.
2. The same connected action with an active explicit grant is reported as executable now.
3. A current approval card appears under `needs_founder_decision`; a merely policy-defined approval action does not.
4. A blocked dependency such as `secretariat.dispatch_formal_record` appears as blocked and never executable.
5. A forbidden action such as vote/result alteration is never executable or preparable.
6. A quiet domain brief uses concise prose and leaves raw counts to metric cards.
7. Morning brief agency is exception/current-work driven rather than a dump of the complete authority matrix.
8. The Blade view renders all four lanes and performs no client-side permission inference.
9. Existing Phase 3 tests remain green.
10. Full project validation must pass before Phase 4 is called complete.

## UAT success criteria

From the founder's perspective, a report must answer, without opening another screen:

- What is happening?
- Does it need my attention?
- What has Najm Hoda already prepared?
- What can Najm Hoda legally and technically do itself right now?
- What specifically requires my decision?
- What is blocked and why?

The UI must never imply that a report-only capability is autonomous execution, and it must never imply that an action happened unless the canonical lifecycle records that outcome.