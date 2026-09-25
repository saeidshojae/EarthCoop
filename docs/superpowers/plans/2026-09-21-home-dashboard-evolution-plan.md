# Home Dashboard Evolution Plan

**Status:** Planned after the current Location/Registration UAT is closed  
**Constraint:** Do not redesign, remove, or change the behavior of the existing three group summary cards as part of this plan. Implement Home work as a separate task/branch after the current UAT.

## Purpose

Home is the user's persistent home inside EarthCoop. It must answer two different questions according to the user's state:

- New user: «EarthCoop چیست و قدم بعدی من چیست؟»
- Established user: «امروز در EarthCoop چه چیزی به من مربوط است؟»

The current three group cards remain an important snapshot of the user's social/group position, but they are not sufficient onboarding by themselves.

## Layer 1 — «شروع کار با EarthCoop / قدم‌های بعدی من»

Add a compact, state-driven onboarding checklist near the top of Home. It must derive completion from real system state rather than static text. Candidate actions:

- registration and primary residence completed;
- relevant automatic group memberships established;
- open Najm Bahar financial account;
- optionally refine local residence to Street / Alley / Complex / Building;
- complete profile, profession and specialties where needed;
- invite friends through the current participation-credit invitation model;
- explore «گروه‌های من»;
- make a first meaningful participation (discussion/poll/proposal as supported by the product);
- learn the election/governance rules.

The checklist must not permanently dominate Home. When mostly complete it should collapse to a compact progress card (for example, completion percentage plus remaining actions); when complete it should yield space to day-to-day content.

## Layer 2 — Existing group summary cards

Preserve the existing three Home cards exactly in visual structure and existing behavior:

- گروه‌های عمومی
- گروه‌های تخصصی
- گروه‌های اختصاصی

Their counts and sidebar «گروه‌های من» badge must continue to come from real memberships. Do not solve missing-group problems by hiding/changing these cards; fix membership/materialization at its source.

## Layer 3 — «آنچه الآن به من مربوط است»

Below the group cards, progressively introduce contextual current activity rather than building everything at once. Candidate modules include current elections, relevant polls, meaningful recent activity in the user's groups, projects requiring participation, important notices, and later Najm Bahar status/actions.

## Admin slider and managed text

Keep admin-managed slider/text as editorial communication (announcements, feature introductions, campaigns, public guidance). Do not use it as a substitute for the state-driven onboarding checklist. Its placement should remain controlled and should not obscure the user's next actions.

## Najm Hoda integration direction

Najm Hoda can later use the same state model to provide contextual guidance from Home: explain group membership, suggest the next incomplete onboarding action, guide Najm Bahar account opening, or help the user understand relevant participation. Hoda should complement the dashboard rather than replace explicit navigation/actions.

## Invitation wording rule

Invitation guidance must reflect the current economic contract: invitation is tied to participation credit that can contribute to activation under the applicable rules. Do not restore the obsolete fixed cash/Bahar invitation reward language.

## Delivery sequence

1. Close current Location/Registration UAT without expanding Home scope.
2. Inventory existing Home/admin-slider behavior and preserve the three existing group cards as regression fixtures.
3. Define a backend read model for onboarding state and contextual Home actions.
4. Implement the dynamic onboarding checklist with responsive/mobile UX.
5. Add progressive «آنچه الآن به من مربوط است» modules, starting from already-supported elections/polls/activity.
6. Integrate Najm Hoda contextual guidance only after the explicit dashboard actions are stable.
7. Run dedicated visual/functional UAT for new users and established users.

## Acceptance invariants

- Existing three group cards are not visually redesigned by this task unless separately approved.
- Existing group counts and sidebar badge remain accurate and membership-backed.
- Home does not show a completed action as pending or vice versa.
- New users receive clear next actions without requiring admin-authored content.
- Established users are not forced through permanent onboarding clutter.
- Mobile Home remains readable without horizontal overflow.
