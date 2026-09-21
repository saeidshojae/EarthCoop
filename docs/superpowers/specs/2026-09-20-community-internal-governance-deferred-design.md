# Community Internal Governance and Election — Deferred Design Contract

**Date:** 2026-09-20  
**Status:** Deferred / approved direction, not an implementation task in the current Location/Governance closure  
**Priority:** Later. Do not block the current Location/Governance stabilization and closure.

## Purpose
This document preserves the agreed direction for selecting managers and inspectors of optional local Communities so the design is not lost while the project prioritizes bringing Location/Governance to a reliable, closable state.

It is a design contract and future-work marker. It does not authorize routing Community groups through EarthCoop's systemic election topology.

## Existing boundaries that must remain true
- A Community is optional and scoped to an eligible micro-location such as Street, Alley, Complex, or Building.
- Residence does not automatically enroll the resident in a Community.
- Community membership is explicit opt-in. Join/Leave controls membership.
- Creating a Community is optional. The creator may become a member as a consequence of the explicit create action.
- A Community GovernanceArea has area_kind=community; it is not an official GovernanceArea.
- A Community Public Assembly Group must not enter official canonical membership topology or upstream representation.
- A Community must never open a Systemic Election.
- Community membership and Community managers/inspectors must not affect the threshold, electorate, candidacy, appointments, or representation of official systemic elections.
- Canonical roles remain: 0 observer, 1 active member, 2 inspector, 3 manager, 4 guest, 5 temporary active.
- Role 5 is temporary operational participation and must not silently become a governance/electoral membership role.

## Target lifecycle
Future Community governance:

**Location → optional Community → explicit membership → Community electorate → Community internal election → Community managers/inspectors → Community-local permissions**

Official governance remains separate:

**Primary Residence → official GovernanceArea → canonical official Group → Systemic Election → official managers/inspectors → official representation**

The lifecycles may reuse technical primitives, but their legitimacy and topology must never be conflated.

## Election model
Do not build a second copy of the whole election engine if the existing lifecycle can be safely decomposed and reused.

The intended architecture is a shared lower-level election lifecycle with an explicit election scope/type boundary, conceptually:
- systemic — official EarthCoop governance election.
- community_internal — election governing only one Community.

The exact schema/API name is deferred until implementation audit. Do not overload an ambiguous existing flag if doing so could allow Community elections to leak into systemic topology.

The existing Poll main_type=0 internal-election mechanism must be audited before reuse. It must not be promoted into a manager-appointment mechanism merely because it is called an internal election. If it is only a poll/simple vote, the professional election lifecycle should instead be factored into reusable lower-level services with separate systemic and Community scopes.

## Membership and electorate
Community residence and Community membership are different facts. Only a user who explicitly joined the Community can receive Community membership rights.

Default future electoral contract:
- role=1 active Community member: elector/selectable member.
- role=0 observer: no Community governance vote.
- role=4 guest: no Community governance vote.
- role=5 temporary active: operational participation only; no Community governance vote or candidacy by default.
- role=2/3: inspector/manager office roles after appointment; lifecycle rules must define historical/current eligibility without confusing officeholding with ordinary electorate membership.

Any future deviation must be an explicit policy decision, not an incidental consequence of GroupPolicy.

## Threshold and settings
Community elections need their own policy/settings.

Do not hard-code the official systemic default threshold into Community elections. Community threshold, timing, manager seats, inspector seats, acceptance window, periodic review, vacancy handling, and other lifecycle settings must be configurable at the appropriate scope.

Default numbers are intentionally not decided by this document and must be approved before implementation.

## Creator/bootstrap rule
The Community creator is not the permanent owner or permanent manager merely because they created the Community.

Creation is an explicit operational action. The creator becomes a Community member under the current creation contract.

If temporary administration is needed before the first valid internal election, implement it explicitly as a bootstrap/caretaker state with bounded powers and a clear termination condition. Do not disguise creator status as elected legitimacy.

## Appointment semantics
A Community internal election may appoint role=3 Community manager and role=2 Community inspector.

Those roles are local to the Community Public Assembly Group/governance scope.

A Community manager or inspector:
- is not thereby an official EarthCoop manager/inspector;
- receives no upstream official representation;
- must not create or mutate an official GovernanceArea;
- must not satisfy official responsibility/office rules merely because of the Community office;
- must not create an official ElectionAppointment semantic unless the appointment model has been explicitly generalized with a safe scope discriminator.

## Lifecycle capabilities
Future implementation should cover, using shared primitives where safe:
- threshold readiness;
- opening the Community internal election;
- immutable electorate/candidate eligibility snapshot where appropriate;
- voting and result calculation;
- responsibility offer/accept/reject and next-in-line replacement;
- manager/inspector appointment;
- resignation/revocation/vacancy;
- planned succession or periodic renewal if adopted;
- audit history;
- Community-local permissions derived from current appointments.

## Required safety invariants
Implementation is incomplete until automated regressions prove at least:
1. A Community can never create a systemic election cycle.
2. Community members never count toward an official systemic threshold merely through Community membership.
3. A Community internal ballot cannot appear as an official current election.
4. Community manager/inspector appointments do not create official upstream representation.
5. Official membership reconciliation cannot remove or reinterpret explicit Community membership.
6. Community membership remains opt-in across residence changes; no automatic destination Community join.
7. role=5 cannot gain Community governance rights accidentally through generic participation policy.
8. Community creator status alone never grants permanent elected authority.
9. Leaving or becoming ineligible is handled by explicit office/vacancy rules rather than contaminating official governance.
10. Community and official election histories remain distinguishable in UI, APIs, audit records, and reporting.

## UI direction
In My Location & Governance → Local Communities, each Community should eventually expose membership state and Join/Leave, active member count, current Community managers/inspectors, internal election status, and entry to an active internal election.

Use clear wording such as **Community internal election / انتخابات داخلی اجتماع محلی**, distinct from official/systemic governance elections.

## Deferred implementation sequence
When this becomes a priority:
1. Audit current Poll internal-election and systemic lifecycle primitives.
2. Write explicit Community election scope/type and migration contract.
3. Add RED boundary tests before behavior changes.
4. Extract/reuse shared election primitives without weakening systemic invariants.
5. Implement Community policy/settings and eligibility.
6. Implement Community-local appointment lifecycle.
7. Add UI/API surfaces.
8. Run focused regressions, then full validation.
9. Perform manual UAT proving Community and official governance remain separate.

## Current project priority
Do not start this implementation in the present workstream.

Immediate priority is bringing **Location/Governance** to a stable, dependable, closable state, including remaining structural-claim hardening and post-cutover UI/mobile/manual-UAT work. After that, return to the broader pre-mobile development/stabilization roadmap before building the mobile application.

This document exists so Community internal governance can be resumed later without reopening or guessing the architectural decisions above.
