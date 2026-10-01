# Communication Center Design — Self Review

**Spec reviewed:** `docs/superpowers/specs/2026-09-30-earthcoop-communication-center-design.md`

## Result

Approved for planning, with the clarifications already incorporated into the spec.

## Checks performed

### 1. Architecture fit

The design preserves existing working capabilities and migrates incrementally instead of rewriting all mail flows at once. This is the correct fit for EarthCoop because current mail behavior is spread across direct `Mail::*` calls, Mailables, Notifications, the admin email tools, and ticket-email integration.

### 2. Scope control

The design separates v1 from later capabilities. Email is the only active v1 channel; SMS, WhatsApp, advanced marketing automation, arbitrary query builders, and autonomous AI sending are explicitly outside scope.

### 3. Three automation modes

All requested modes are represented as first-class behaviors: event-driven, scheduled/recurring, and conditional/delayed.

### 4. User preference model

The design distinguishes required transactional, operational, and optional communications. Required transactional mail cannot be suppressed by optional-email preferences. Operational weekly reports remain user-configurable. Registration welcome is operational and default-on.

### 5. Sender identity correction

The design does not incorrectly collapse `SystemIdentityService` into an email-only concept. Communication sender identities remain separate but may resolve/reference an existing system identity where that system actor relationship is relevant.

### 6. Registration welcome contract

The welcome email is triggered only on the canonical transition to completed three-stage registration, is queued asynchronously, has a deterministic dedupe key, and is required to remain single-send under event/job replay.

### 7. Weekly reports

Member, manager, and inspector reports use dedicated context builders, per-recipient context, deterministic period dedupe, user preferences, and canonical EarthCoop responsibility state.

### 8. Admin safety

Rules are declarative and registry-backed; admin cannot execute arbitrary SQL, PHP, or free code. Bulk sending requires audience preview, test/sample rendering, recipient estimate, and confirmation. Pause/cancel affects only pending delivery.

### 9. Reliability

The design includes queued sending, priority classes, bounded retries, permanent failure visibility, append-only attempts, rate-aware bulk delivery, and storage-level deduplication.

### 10. Auditability

Published template versions are immutable. The system records the exact template version, sender identity, context snapshot, preference decision, recipient state, and delivery attempts needed to explain a sent communication.

### 11. Migration safety

Authentication/invitation mail is deliberately migrated after the communication core is proven. Ticket threading and Message-ID behavior are preserved during support migration. Direct `Mail::*` calls are guarded only after approved migration paths are complete.

### 12. Security and privacy

Provider credentials remain in environment/secret configuration. Template variables are validated and escaped/normalized. Context snapshots are minimized rather than copying unrestricted domain records. Permissions for templates, rules, senders, campaigns, and retries are distinct.

### 13. Deliverability

The design correctly treats SPF, DKIM, DMARC, sender-domain alignment, worker health, scheduler health, and provider limits as production requirements rather than hiding them in application tables.

### 14. Future Najm Hoda integration

Najm Hoda may prepare drafts or recommendations but cannot bypass Communication Center policies, permissions, preferences, approvals, or audit.

## Clarifications introduced during review

- Sender identity and in-app system identity are related but not the same domain object.
- Registration welcome is operational/default-on rather than security-critical mandatory mail.
- Provider bounce/complaint webhooks are future adapter capabilities, not required for SMTP-only initial delivery.
- Digest storage is deferred until a real digest release/use case rather than created speculatively.
- Recipient-local-time scheduling is future-compatible but not required for v1.
- Architectural prohibition on direct `Mail::*` is a final migration gate with documented framework/infrastructure exceptions, not an immediate breaking rule.

## Conclusion

No architectural blocker remains. The design is coherent, implementable incrementally, aligned with the requested operating model, and appropriately scoped for a test-first implementation plan.
