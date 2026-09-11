# EarthCoop Global Location, Governance, Residency & Grouping Architecture

**Status:** Approved design specification for implementation planning  
**Date:** 2026-09-10  
**Baseline:** `main@767a276b962a62234f70878071d007930903577a`  
**Scope:** Location Core, governance scopes, residency, multidimensional auto-grouping, community areas, reference geography, crowdsourcing, geolocation, and controlled replacement of the legacy fixed geography model.

## 1. Purpose

EarthCoop must operate globally without forcing every country into Iran's current fixed hierarchy (`continent → country → province → county → district → city/...`). At the same time it must preserve the social and governance meaning of place: urban and rural residents must both reach valid EarthCoop governance scopes, and the same spatial backbone must support public assemblies as well as profession, specialty, age, and gender groupings.

The target architecture separates four concerns:

1. **Location** — what/where a real-world place is.
2. **Governance Area** — what spatial/community scope EarthCoop governs or serves.
3. **Residency and other user-place relationships** — how a person relates to a place.
4. **Capabilities and membership** — what an area can do and which groups a user belongs to.

The current production/test users and current location IDs are not architectural compatibility constraints. Existing accounts are test users. We may rebuild test/UAT data when the new architecture is ready. Correct existing EarthCoop capabilities must nevertheless be protected by regression tests throughout development.

## 2. Core architectural decision

EarthCoop will use a **generic Location Tree plus an independent Governance Tree**.

A Location is not a Group and is not a Governance Area. A Governance Area may map to one or more Locations. A Group belongs to a Governance Area and represents a membership dimension within that scope.

Canonical flow:

```text
User
  → Primary Residence
    → Location
      → Governance Area(s)
        → Membership Engine
          → Public / Profession / Specialty / Age / Gender groups

Governance Area
  → Public Assembly
  → Elections
  → Secretariat
  → Projects
  → Polls
  → Dimension Groups
```

This separation is mandatory. Election topology must ultimately depend on Governance Areas, not directly on government geography tables.

## 3. Location Core

### 3.1 Generic tree

`locations` forms the canonical geographic tree. Each location has one canonical parent. The runtime must not require globally fixed columns such as `province_id`, `county_id`, `city_id`, or `alley_id`.

Conceptual fields include:

- stable ID
- `parent_id`
- country/schema context
- `location_type_id`
- canonical and localized names
- lifecycle/status
- optional centroid latitude/longitude
- validity interval
- provenance/audit metadata

The exact migration column set will be finalized during implementation planning.

### 3.2 Country-aware type/schema model

`location_types`, `location_schemas`, schema/type membership, and allowed type relations describe valid structures. A schema is not a single linear sequence. It must support branching.

For Iran, for example:

```text
... → بخش
      ├→ شهر → منطقه شهری → محله → خیابان → optional micro-locations
      └→ دهستان → روستا → محله → optional lower locations
```

A rural resident must never be forced through a city node. A village without a neighborhood must still be capable of being a valid residence endpoint and, by policy, a base governance scope.

Country policy determines the minimum required registration resolution. For Iran, reference data should be used as deeply as it is reliable; lower micro-locations remain optional.

### 3.3 Micro-local is a family, not a rigid level

EarthCoop must support types such as street, alley, block, residential complex, building, campus, and future equivalents. Their relationship is flexible. For example, a residential complex may be directly under a street or under an alley. The architecture must not force `street → alley → complex → building`.

### 3.4 External identifiers and provenance

Locations may carry multiple external identifiers through a separate mapping such as `location_external_ids`, including statistical/government identifiers, municipal identifiers, OSM/GeoNames identifiers, or EarthCoop-specific identifiers.

Names are display data, not identity keys. Reference imports must be source/version aware and auditable.

### 3.5 Historical lifecycle

Used locations are not destructively deleted merely because real-world geography changes. Lifecycle states/relations must support concepts such as active, inactive, superseded, merged, and split.

Successor/predecessor relationships preserve historical interpretation of old elections, projects, documents, and memberships. A change in government geography does not automatically mutate EarthCoop Governance Areas.

## 4. Reference geography and user-created geography

### 4.1 Managed reference data

For each country, reliable official/reference geography should be imported and managed centrally as far down as dependable datasets permit. Ordinary users do not directly create authoritative reference records at those managed levels; they submit corrections/proposals.

The boundary is country-configurable. If reliable street data is unavailable in a country, street creation may itself become community-assisted.

### 4.2 Reference data importer

Large real-world geography is not a normal production Seeder. EarthCoop will use a versioned, idempotent, auditable reference-data importer with validation and dry-run/diff support.

Conceptually:

```text
Dataset → Normalize → Validate → Dry-run/Diff → Apply → Audit
```

The current Iran geography can be extracted/normalized as source material, but current numeric IDs need not constrain the new canonical IDs.

### 4.3 Crowdsourced locations

Where policy permits, a user may propose a missing micro-location. The proposal becomes selectable in a visibly pending state so later users select the same candidate instead of creating duplicates.

Verification evidence is stored per distinct user, not merely as a counter. A configurable threshold (initial example/default: 10 distinct users) means **ready for review**, not unconditional automatic approval.

Admin/Najm Hoda review can approve, reject, merge duplicates, or request more evidence. Duplicate detection should run before creating a new proposal.

Location verification and Community creation are separate decisions. A verified residential complex does not automatically create an EarthCoop Community Area.

## 5. Geolocation and reverse geocoding

Registration/profile flows may offer an optional consent-based **Detect my location** action.

```text
Browser/mobile geolocation
  → latitude/longitude
  → provider abstraction
  → reverse geocoding
  → normalization
  → Location Core matching
  → confidence/result shown to user
  → user confirmation/correction
```

GPS is assistive evidence, never authoritative proof of residence. A user may be at work or travelling. Conflict with manually selected residence must not block registration; it simply does not count as GPS-consistent evidence for that residence.

Manual selection must always remain available. Precise raw coordinates should not be retained indefinitely without a defined purpose and consent. The provider must be abstracted so EarthCoop is not locked to one geocoding vendor.

## 6. Governance Areas

### 6.1 Independent governance tree

`governance_areas` forms an EarthCoop governance/community topology independent of the Location tree. `governance_area_locations` maps one Governance Area to one or more Locations.

A Location may exist without a Governance Area. A government boundary change therefore does not automatically rewrite EarthCoop governance history.

### 6.2 Official vs Community Governance

EarthCoop has two related but distinct classes of spatial organization:

- **Official Governance Areas** — participate in EarthCoop's formal governance/election topology.
- **Community Areas** — optional resident communities such as street, alley, complex, building, block, or similar scopes.

Community Areas may use assemblies, chat, secretariat, polls, projects, and internal election tools without automatically participating in the formal continuous systemic election topology.

### 6.3 Base official governance scope

The formal chain must not hard-code literal `type = neighborhood` as its universal starting point.

Policy identifies the smallest valid **base official governance scope** for a path. Common examples:

- urban location with neighborhoods → neighborhood;
- rural village with neighborhoods → village neighborhood;
- small village without neighborhoods → village;
- another country → its configured conceptual equivalent.

Thus urban and rural residents have equal access to the formal governance architecture.

### 6.4 Globally consistent conceptual governance

EarthCoop keeps a globally consistent conceptual governance hierarchy while allowing country mappings, localized labels, skipped tiers, and justified intermediate tiers. Government administrative labels do not define EarthCoop's governance semantics.

The exact canonical tier catalogue/ranks will be finalized in implementation planning without binding the core to Iran-specific names.

## 7. Capability policy

Governance/Community capabilities are policy-driven rather than hard-coded by location type.

Capabilities include at least:

- public/general assembly
- chat
- secretariat
- polls
- projects
- internal elections
- systemic EarthCoop elections
- managers/inspectors where applicable
- delegation where applicable
- official upstream participation
- automatic membership/group creation behavior

Policy follows inheritance with controlled overrides, conceptually:

```text
EarthCoop default
  → country / governance-type policy
    → specific area override
```

Admin policy defines which settings residents are themselves allowed to change democratically. Community preference operates only inside those permitted bounds.

## 8. Residency and user-place relationships

### 8.1 Primary residence

A user has a canonical Primary Residence pointing to one Location leaf/end-point. Ancestors are derived from the Location tree rather than redundantly stored as a fixed set of geographic FKs.

Only Primary Residence grants official geographic governance membership/voting rights.

### 8.2 Other relationships

Users may also have work, study, or other location relationships. These may support communities/services/professional context but do not create parallel official geographic voting rights.

### 8.3 Residence history and changes

Residence changes are historical, not destructive. Previous records remain effective for their historical intervals.

Default policy: at most **2 explicit Primary Residence transfers per rolling 12 months**, configurable by admin. Emergency/correction overrides require a reason and audit trail.

GPS re-detection or display/address correction that does not transfer Primary Residence does not consume this quota.

Past votes, projects, roles, minutes, and documents are never rewritten merely because the user later moves.

## 9. Multidimensional grouping

### 9.1 Geographic scope is the shared backbone

Public assemblies and profession, specialty, age, and gender groups all use the same Governance Area as their spatial scope.

Conceptually:

```text
Group = Governance Scope × Membership Dimension × optional Dimension Value
```

Examples:

- `Balanahle Governance Area × Public`
- `Balanahle × Profession × Farmer`
- `Sari × Specialty × Civil Engineering`
- `Iran × Age × 18–25`
- `Iran × Gender × Female`

Groups do not directly interpret raw Location IDs.

### 9.2 Extensible dimensions

The target design should avoid permanently encoding every dimension as a separate geographic/group schema column. Dimensions are explicit domain concepts with their own resolvers/validators; they are not an unvalidated arbitrary key/value system.

Initial dimensions are:

- public
- profession/sector
- specialty
- age cohort
- gender

Future legitimate dimensions can be added without redesigning Location Core.

### 9.3 Creation policy and anti-explosion behavior

Not every theoretical `Governance Area × Dimension Value` becomes a physical group. Creation/activation policy supports modes such as:

- automatic
- threshold-based
- on-demand
- disabled

Thresholds are configurable by scope/tier/dimension. This prevents millions of empty micro-local profession/specialty groups.

A micro-community may, for example, have a public assembly on demand while profession/specialty/age/gender auto-groups are disabled.

## 10. Membership Engine

Membership resolution takes user attributes plus Primary Residence, resolves applicable Governance Areas, and applies dimension and creation policies.

```text
Primary Residence
  → applicable Governance Areas
  → Public eligibility
  → Profession eligibility
  → Specialty eligibility
  → Age eligibility
  → Gender eligibility
  → policy/threshold evaluation
  → memberships
```

The engine must be deterministic, auditable, and independently testable. It must not scatter location-specific membership logic through controllers.

Official upstream membership derived from Primary Residence and below-base Community membership are separate policy concerns. Community auto-join/creation remains configurable.

## 11. Group and subsystem boundaries

A Governance Area is not itself a Group.

A Governance Area is the common scope to which capabilities attach:

```text
Governance Area
  ├ Public Assembly Group
  ├ Secretariat
  ├ Elections
  ├ Projects
  ├ Polls
  └ Dimension Groups
```

This allows Elections, Secretariat, Projects, Groups, and Najm Hoda to share a stable scope without each learning country-specific geography.

The legacy `groups.address_id + groups.location_level` model is not part of the target architecture. Runtime consumers must ultimately use explicit canonical scope relationships.

## 12. Election boundary

Formal continuous EarthCoop systemic elections operate on the Official Governance topology. They must ultimately resolve parent/child topology from Governance Areas, not from `continents`, `countries`, `provinces`, etc.

Below the configured formal base, Community Areas may use internal election forms/tools for their own managers or boards. An internal Community election does **not** make those officeholders official EarthCoop managers/inspectors unless explicit policy says otherwise.

The boundary between Community and formal governance is configurable; it is not a hard-coded database condition.

## 13. Najm Hoda role

Najm Hoda acts initially as an administrative intelligence/review assistant, not an autonomous authority for sensitive geography/governance decisions.

Examples:

- surface new location proposals;
- identify proposals reaching verification threshold;
- flag probable duplicates;
- summarize independent/GPS-consistent evidence;
- recommend approve/reject/merge/review;
- report dimension groups reaching activation thresholds;
- surface anomalous or suspicious verification patterns.

Sensitive actions remain approval-gated until an explicit capability policy later delegates them.

## 14. Admin experience

A Location/Governance control center should eventually expose, as coherent workflows rather than raw tables:

- reference geography
- pending locations
- duplicate candidates
- verification queue
- import history
- location lifecycle changes
- governance mappings/topology
- capability/grouping policies

All consequential actions are audited.

## 15. Replacement strategy for the current architecture

### 15.1 Compatibility stance

Current users are test users and current geography IDs/data relationships are not a long-term compatibility requirement. Therefore the project will **not** build a permanent dual-runtime compatibility architecture merely to preserve current test records.

However, the codebase contains mature capabilities that must not regress. Development remains isolated, test-first/checkpointed, and `main` remains untouched until an explicitly reviewed integration stage.

### 15.2 Target-first sequence

Recommended implementation sequence:

1. Baseline inventory and regression contracts for current Registration, Address/Profile, Grouping, Groups, Elections, Secretariat, Projects, and geography APIs.
2. Global Location Core and country-aware schema/type relations.
3. Governance and Residency core.
4. Versioned Iran reference dataset/importer, including both urban and rural branches.
5. Schema-driven Registration/Profile location UX.
6. New Membership/Auto-grouping Engine and multidimensional group model.
7. Integration of Groups, Elections, Secretariat, Projects, and other scope consumers with Governance Areas.
8. Community/micro-location workflows.
9. Crowdsourcing, geolocation/reverse geocoding, verification, and Najm Hoda review assistance.
10. Full-system validation and fresh-database bootstrap rehearsal.
11. UAT with test users.
12. Controlled production cutover before onboarding real users.
13. Remove/deprecate legacy runtime geography dependencies only after repository/runtime verification proves they are unused.

These are architectural phases, not necessarily one PR each. The implementation plan must split them into small testable checkpoints.

### 15.3 Fresh bootstrap is allowed

Because current users are test users, a fresh database bootstrap may be used for development/UAT and, after explicit cutover approval/backup, for the pre-real-user production transition. This permission does **not** mean destructive commands may be run casually against production during development.

Existing useful Iran reference content should be normalized into the new dataset/importer rather than forcing the new schema to preserve legacy numeric IDs.

## 16. Safety, rollout, and rollback

Code development remains on isolated branches. No direct `main` modifications are permitted during implementation.

Prefer additive/target-first development until the new path is proven. Feature flags may be used where they materially improve UAT or rollback, especially around registration and subsystem cutovers.

Rollback should preferentially mean switching a consumer/path back before final cutover, rather than attempting destructive reverse-data migrations.

A final destructive cleanup or production fresh bootstrap requires its own explicit checkpoint, backup, validation, and user approval.

## 17. Required testing strategy

Testing must cover four layers:

1. **Unit:** Location Schema/Resolver, Governance Resolver, Residency rules, Membership/Capability policies.
2. **Integration:** Registration → Residence → Governance → Membership → Groups/Elections/Secretariat/Projects.
3. **Regression contracts:** preserve correct existing EarthCoop capabilities while replacing their spatial backbone.
4. **Fresh-bootstrap/UAT:** prove the target schema can be created from zero and populated with reference data reliably.

Permanent representative scenarios include:

- urban Sari resident;
- rural Chahardangeh path (`بخش → دهستان → روستا → محله` where applicable);
- village with no neighborhood;
- urban neighborhood and street;
- alley/residential complex/building Community;
- location proposal and duplicate selection;
- threshold verification;
- GPS match and GPS/manual conflict;
- Primary Residence transfer and quota;
- work/study relationship without official voting rights;
- public, profession, specialty, age, and gender membership;
- threshold/on-demand/disabled group creation;
- official election topology;
- Community internal election;
- location rename/merge/split with historical integrity.

Existing full validation suites for Governance, Elections, Groups/Group Chat/Admin, Secretariat, Najm Hoda, Najm Bahar, Stock, JavaScript, and full PHPUnit remain release gates as applicable. A geography improvement is not acceptable if it silently breaks another mature subsystem.

## 18. Explicit non-goals / deferred items

The first implementation does not need to solve all future geospatial concerns. In particular:

- nationwide pre-seeding of every street/alley/building in every country is not required;
- full polygon/GIS boundary infrastructure may be added later unless required by the chosen reference/reverse-geocoding integration;
- Najm Hoda autonomous approval of sensitive changes is deferred;
- below-base Community Areas do not join formal systemic election topology by default;
- arbitrary unvalidated group dimensions are not introduced;
- permanent legacy compatibility is not a goal.

## 19. Acceptance criteria for the architecture program

The architecture is successful when all of the following are true:

1. Registration can follow country-specific branching geography without fixed province/county/city assumptions.
2. An urban resident and a rural resident resolve through the same Location → Governance → Membership contract.
3. A village without neighborhoods can still obtain a configured base official governance scope.
4. Public and profession/specialty/age/gender groups use Governance Areas as their geographic scope.
5. Group creation policy prevents empty-group explosion.
6. Micro-locations can exist without Community Areas; Communities are created only when policy/demand requires them.
7. Primary Residence is the sole basis of official geographic voting membership, while work/study relationships remain representable.
8. Formal elections no longer depend on country-specific geography tables.
9. Community internal elections remain distinct from formal EarthCoop systemic elections.
10. Location history supports rename/merge/split without erasing historical referents.
11. Reference geography is versioned/imported rather than maintained as a giant production Seeder.
12. User-proposed locations have deduplication, distinct-user evidence, configurable review thresholds, and audited approval workflows.
13. Optional geolocation can assist without overriding user-declared residence.
14. Existing mature EarthCoop capabilities remain green under the project's validation gates.
15. A clean database can bootstrap the new architecture and complete representative urban/rural UAT before real-user onboarding.

## 20. Implementation planning constraint

This document authorizes **planning**, not implementation. The next session must begin by reviewing this spec and current repository state, then use a dedicated implementation-planning workflow to break the program into safe, dependency-ordered, test-first checkpoints. No migration/code work should be inferred as approved merely from approval of this architecture document.
