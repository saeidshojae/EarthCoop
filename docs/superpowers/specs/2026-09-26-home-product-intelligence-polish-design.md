# Home Product Intelligence & Polish — Design

**Status:** Approved direction from 2026-09-26 visual UAT

## Goal

Turn the redesigned Home from a polished feature gateway into a state-aware personal civic dashboard for EarthCoop, while preserving the Welcome/Register visual language.

## Product principles

1. Home answers five questions quickly: where am I, what am I part of, what changed, what needs my action, what should I do next.
2. User state must come from existing domain authorities/read models; no duplicated business rules in Blade.
3. The visual reference remains Welcome/Landing + Register Step 1/2.
4. Mobile and desktop are separate compositions of the same information, not scaled copies.
5. The green/blue/gold identity stripe is an identity signature, not a repeated border on every section.
6. Admin-managed slider/content stays available but is visually secondary to the user's own state.
7. Najm Hoda's floating launcher must stay reachable without covering content or primary actions.
8. No C14, election logic, membership logic, wallet logic, or Location/Governance behavior is changed by this work.

## Scope

### A. Visual restraint
- Keep the tri-color stripe on the primary Hero identity surface.
- Remove repeated identity stripes from secondary Home sections.
- Reduce visual weight of the admin/news section.
- Keep current EarthCoop button/card/motion tokens and restrained typography.

### B. State-aware journey
The four journey cards remain:
- Location & Governance
- Najm Bahar
- My Groups
- Invite & Participate

Each card receives real state from existing authorities, e.g. completed/active/pending counts, group totals, invitation capacity. Cards should become more compact on mobile once state is complete.

### C. Next Best Action
Replace the static recommendation with deterministic priority rules derived from existing system state. Initial priority order:
1. missing/unfinished residence (defensive fallback only; Home normally redirects before render),
2. missing Najm Bahar main account,
3. election action required,
4. active poll still needing a vote,
5. invitation capacity available,
6. otherwise return to Location/Governance as the local civic anchor.

No AI decision is required for this first version. The result must be explainable and testable.

### D. Today in EarthCoop
Add a compact action/status surface based only on stable existing signals:
- unread notifications,
- election action-required count from `CurrentElectionCenterService`,
- active polls in user's groups that the user has not voted in,
- pending location-group requests when canonical groups are enabled.

If all counts are zero, show a calm zero-state rather than four empty cards.

### E. Sidebar hierarchy
Do not redesign navigation architecture. Add lightweight visual grouping/section labels to reduce same-weight scanning on desktop while keeping existing routes and mobile collapse behavior.

### F. Najm Hoda launcher safe area
On Home/mobile, keep the launcher clear of bottom content/CTAs/footer and use safe-area-aware offsets. Do not alter chat capabilities.

## Non-scope

- Rewriting Sidebar navigation routes.
- Building an AI recommendation engine.
- New notification/event infrastructure.
- Reworking Registration Step 3 styling (separate follow-up).
- Broad design-system refactor.
