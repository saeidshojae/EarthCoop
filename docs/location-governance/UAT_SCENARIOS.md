# Location/Governance Permanent UAT Scenarios

These scenarios are the permanent acceptance matrix for the global Location/Governance architecture. Use test/UAT users and disposable test data only. No scenario authorizes production cutover or destructive production work.

| ID | Scenario | Expected result | Permanent regression |
|---|---|---|---|
| LG-UAT-01 | Urban Sari residence | Country → province → county → section → city is accepted as a canonical residence path; deeper urban region/neighborhood/street remains valid where present. | `RegistrationPrimaryResidenceTest`, `GlobalArchitectureScenarioTest` |
| LG-UAT-02 | Chahardangeh rural residence | Country → province → county → section → rural district → village resolves without requiring the urban branch. | `RegistrationPrimaryResidenceTest`, `GlobalArchitectureScenarioTest` |
| LG-UAT-03 | Village without neighborhood | An explicit `no_neighborhood` structural claim makes Village the registration/governance base without creating a fake Neighborhood. An open claim is visible but affects only a user who explicitly selects it; during registration Street and finer micro-location levels remain deferred. Until the selected claim is approved, the Village base is presented as pending and does not gain formal active/election membership. | `ResidencePickerDeepHardeningTest`, `RegistrationStructuralClaimEntryPointTest`, `HierarchicalCanonicalMembershipTest` |
| LG-UAT-04 | Urban neighborhood/street | Urban region → neighborhood → street follows the canonical schema graph and can be selected where endpoint rules allow. | `GlobalArchitectureScenarioTest`, `LocationSelectionApiTest` |
| LG-UAT-05 | Street/alley/complex/building Community | Every approved Street, Alley, Complex, and Building in the residence path may independently offer an optional local Community. Residence never auto-joins a Community; membership is explicit Join/Leave. Community remains outside official systemic-election topology. | `MyLocationGovernancePageTest`, `CommunityAreaUiTest`, `CommunityAreaCreationTest`, `CommunityElectionBoundaryTest` |
| LG-UAT-06 | Duplicate location proposal | A likely/canonical duplicate is reused or surfaced instead of silently creating a second canonical place. | `LocationProposalWorkflowTest`, `LocationDuplicateDetectorTest` |
| LG-UAT-07 | Distinct verifier threshold | Repeated evidence by one user counts once; the configured distinct-user threshold moves a proposal only to `ready_for_review`, never auto-approval. | `DistinctVerifierThresholdTest` |
| LG-UAT-08 | GPS match/conflict | GPS is optional assistance only. Match can assist selection; disagreement warns and never overwrites manual residence selection or proves residence. Raw precise coordinates are not persisted as residence proof. | `GeolocationAssistTest`, `GeolocationAssistUiContractTest`, `GeolocationMatchServiceTest` |
| LG-UAT-09 | Residence transfer quota | Primary-residence history is preserved and transfer limits are enforced by policy. | `ProfileResidenceTransferTest`, `PrimaryResidenceHistoryTest`, `ResidenceTransferPolicyTest` |
| LG-UAT-10 | Work/study no vote | Work/study relationships do not enter official governance/voting scope; only active primary residence does. | `NonResidenceRelationshipVotingTest`, `MultidimensionalMembershipTest` |
| LG-UAT-11 | All five membership dimensions | Public, profession, specialty, age, and gender resolve as explicit dimensions from the same canonical governance context. | `MultidimensionalMembershipTest`, `DimensionResolverTest`, `MembershipEngineTest` |
| LG-UAT-12 | Creation modes | `automatic`, `threshold`, `on_demand`, and `disabled` remain explicit policy modes; non-automatic modes do not cause accidental group explosion. | `GroupCreationPolicyTest`, `GroupExplosionPreventionTest`, `MultidimensionalMembershipTest` |
| LG-UAT-13 | Formal election topology | Systemic elections follow official GovernanceArea topology, not Community or arbitrary geography keys. | `OfficialElectionGovernanceTopologyTest`, `ElectionCanonicalCutoverTest` |
| LG-UAT-14 | Community internal election boundary | Community may support internal governance/elections when policy allows but does not become an official upstream systemic-election level. | `CommunityElectionBoundaryTest` |
| LG-UAT-15 | Rename / merge / split history | Rename preserves stable identity and name history; merge/split preserve historical rows and explicit successor relations. | `LocationLifecycleHistoryTest` |
| LG-UAT-16 | Fresh bootstrap | Fresh disposable DB can seed small schema/type/dimension/policy contracts idempotently, with zero real geography rows produced by the seeder itself. | `FreshBootstrapLocationGovernanceTest` |
| LG-UAT-17 | Canonical runtime without legacy Address | With runtime and registration gates enabled, active Primary Residence is sufficient for home/profile completion without creating a legacy Address row; rollback mode still uses Address. | `CanonicalRuntimeAddressIndependenceTest`, `HomeCanonicalResidenceTest` |
| LG-UAT-18 | City without urban region | With an explicit `no_urban_region` structural claim, City may skip Urban Region and expose Neighborhood as the real next residence level. No fake Urban Region is created. | `ResidencePickerDeepHardeningTest` |
| LG-UAT-19 | City without urban region or neighborhood | With explicit `no_urban_region` + `no_neighborhood` claims, City exposes Street directly. A direct Street proposal must carry valid shared structural claims for that City/path; those open claims may have been created by another resident. Approval revalidates them, and the approved Street preserves claim IDs in provenance. | `ResidencePickerDeepHardeningTest` |
| LG-UAT-20 | Region without neighborhood | With an explicit `no_neighborhood` claim, Urban Region becomes the registration/governance base while ordinary residence editing may continue directly to Street; no fake Neighborhood is created. An open claim must be explicitly selected and keeps the Region base non-participatory until human approval; approval materializes the pending base exactly once. | `ResidencePickerDeepHardeningTest`, `RegistrationStructuralClaimEntryPointTest`, `HierarchicalCanonicalMembershipTest`, `CanonicalGroupIndexCutoverTest` |
| LG-UAT-21 | Registration stopping rule | Registration ends at Neighborhood or the deepest structurally available governance/residence base. Street/Alley/Complex/Building are not collected during registration; they remain available after onboarding from profile / My Location & Governance. | `RegistrationPrimaryResidenceTest`, `RegistrationPendingResidenceTest`, `RegistrationStep3CanonicalUxRegressionTest` |
| LG-UAT-22 | Pending micro-location remains post-registration | Pending Street/Alley/Complex/Building proposals never become the registration endpoint. Profile/Admin may still preserve a pending exact-residence intent from the approved anchor, so local detail remains non-blocking after onboarding. | `RegistrationPendingResidenceTest`, `DeepPendingResidenceEntryPointsTest`, `ProfilePendingResidenceTest`, `PendingResidenceIntentTest` |
| LG-UAT-23 | Proposal support versus approval | Pending proposals and structural claims may collect distinct-user support and become ready for review, but support never auto-approves them. Later users can select the same open proposal where valid. | `DistinctVerifierThresholdTest`, `LocationProposalWorkflowTest`, `ResidencePickerDeepHardeningTest` |
| LG-UAT-24 | My Location & Governance separation | The default tab shows official governance from canonical official GovernanceAreas. The Local Communities tab independently lists every eligible micro-location and explicit membership/create/join/leave state. Community never appears in the official chain. | `MyLocationGovernancePageTest`, `CommunityAreaUiTest` |
| LG-UAT-25 | Canonical My Groups counts | My Groups/Home/navigation counts derive from active canonical memberships and `dimension_key`, including upstream observer memberships, while legacy spatial groups and optional Communities are not silently mixed into official category totals. | `CanonicalGroupIndexTest`, Home/group regression contracts |
| LG-UAT-26 | Systemic election electorate | Official systemic election threshold, electorate, current-election surfaces, reminders, ballot UI, vacancy and responsibility flows use the canonical official topology. Active member role=1, elected inspector role=2, and elected manager role=3 retain systemic voting/selectability rights. Observer role=0, guest role=4, temporary-active role=5, and Community membership do not gain systemic voting/selectability rights. | systemic election canonical/eligibility regressions |
| LG-UAT-27 | Responsive/mobile navigation | The responsive/mobile navigation exposes My Location & Governance and the Location/Governance pages remain usable without horizontal overflow, clipped actions, or inaccessible touch controls. Automated responsive contracts are necessary but manual viewport UAT remains required. | `MyLocationGovernancePageTest`, Responsive Contract Validation |

| LG-UAT-28 | Iran reference-settlement search | With both settlement feature flags enabled, Step 3 may search the neutral 1404 settlement catalog only under the current canonical parent whose v1→v2 mapping is `verified_identity`. Results remain explicitly unverified/pending and never appear as operational Locations. | `IranSettlementCatalogSearchTest`, `RegistrationReferenceSettlementResidenceTest` |
| LG-UAT-29 | Settlement registration with verified parent crosswalk | Selecting an existing reference settlement completes onboarding only when its v2 parent maps back to an active IR canonical v1 Location with `verified_identity`. Primary Residence is temporarily anchored to that canonical parent; the exact settlement is stored as a pending claim/intent. No settlement Location or GovernanceArea is created. | `RegistrationReferenceSettlementResidenceTest`, `IranV1V2RuntimeAuditCommandTest` |
| LG-UAT-30 | Settlement parent fails closed | A reference settlement whose parent has no approved crosswalk, or only a non-final mapping such as `municipal_review`, cannot complete Step 3. No partial residence, claim, group shell or governance state is left behind. | `RegistrationReferenceSettlementResidenceTest`, `IranSettlementAnchorResolver` contract |
| LG-UAT-31 | Settlement support threshold is review priority only | Distinct users may claim the same unverified/needs-review settlement. Reaching the configured threshold (default 10) makes the settlement prominent in the admin queue but does not change classification, residential eligibility, governance authorization, residence confirmation or voting rights. | `ReferenceSettlementReviewTest`, `IranSettlementResidenceClaimTest` |
| LG-UAT-32 | Human evidence review | Admin review of a settlement requires a reason; final residential/nonresidential classification additionally requires dated source-backed evidence and a reference. A residential evidence decision still does not create governance authorization or confirmed residence. | `ReferenceSettlementReviewTest` |
| LG-UAT-33 | Nonresidential settlement rejection cleanup | If a settlement is classified nonresidential with evidence, open claims are rejected, settlement-backed PendingResidenceIntent rows are cancelled and pending group shells are rejected immediately. The existing canonical parent residence anchor is not silently moved. | `ReferenceSettlementReviewTest` |
| LG-UAT-34 | Pending settlement groups remain non-authoritative | While exact settlement residence is pending, settlement-linked group shells stay pending and canonical ancestor groups remain observer-only for base-area purposes. No official settlement group, systemic election electorate or active base membership is created. | `RegistrationReferenceSettlementResidenceTest`, `CanonicalGroupMembershipReconciler` settlement guard |
| LG-UAT-35 | Switching pending exact-residence source | Switching between an ordinary LocationProposal and a reference-settlement claim cancels stale pending group shells from the previous exact-residence source. The UI must never show ghost pending groups for both sources. | `RegistrationReferenceSettlementResidenceTest` |
| LG-UAT-36 | Pending settlement shown after registration | My Location & Governance and canonical Profile show the selected settlement name and a clear pending-review message. The same UI must explicitly distinguish exact pending settlement from the current official canonical/governance anchor. | `RegistrationReferenceSettlementResidenceTest`, `MyLocationGovernancePageTest` |
| LG-UAT-37 | Settlement feature flags fail closed | With `IR_SETTLEMENT_CATALOG_ENABLED=false` or `IR_SETTLEMENT_CLAIMS_ENABLED=false`, settlement search/claim/Step 3 flow is unavailable and existing canonical registration remains unaffected. | `IranSettlementCatalogSearchTest`, `IranSettlementResidenceClaimTest`, `RegistrationReferenceSettlementResidenceTest` |
| LG-UAT-38 | v1→v2 runtime audit is read-only | Running `location:iran-v1-v2-runtime-audit` against an approved environment reports residence/proposal/governance/group dependencies and cutover blockers without INSERT/UPDATE/DELETE. Any present v1 identity without reviewed mapping and any present `municipal_review` mapping blocks shared cutover. | `IranV1V2RuntimeAuditCommandTest` |

## UAT execution rules

1. Use only dedicated test/UAT accounts and disposable or explicitly approved non-production data.
2. Record the exact branch SHA and Full Validation run ID used for the session.
3. For every scenario record pass/fail, unexpected UI behavior, and any generated proposal/import/governance identifiers needed for debugging.
4. Never convert a failed scenario into a manual database correction. Fix the code/data contract, rerun automated regression, then repeat UAT.
5. GPS remains optional; a tester must be able to complete canonical location selection manually.
6. Proposal verification must never be treated as administrative approval.
7. Community must not appear in formal upstream election topology unless a future explicitly approved architecture change says otherwise.
8. A successful C12 UAT candidate is still **not** permission for destructive Production work or an unreviewed cutover.
9. Structural claims used to skip canonical levels must be visibly attributable to the same branch and must still be valid when a proposal is approved.
10. Community membership must be tested as opt-in: viewing a Community or changing residence must not silently join the user.
11. Automated Responsive Contract Validation does not replace manual mobile/browser UAT. Record the viewport/device/browser used for manual checks.
12. Reference-settlement support/claims are signals for review, never proof of residential eligibility or governance authorization.
13. During settlement UAT, verify both the positive state (pending exact settlement is visible) and negative state (no settlement Location/GovernanceArea/active base group or vote right exists).
14. Never enable settlement feature flags on Production merely to conduct UAT; use an explicitly approved UAT/staging environment or a disposable local database.

## Suggested focused automated gate

```bash
php artisan test \
  tests/Feature/LocationGovernance/FreshBootstrapLocationGovernanceTest.php \
  tests/Feature/LocationGovernance/GlobalArchitectureScenarioTest.php \
  tests/Feature/LocationGovernance/RegistrationPrimaryResidenceTest.php \
  tests/Feature/LocationGovernance/DistinctVerifierThresholdTest.php \
  tests/Feature/LocationGovernance/GeolocationAssistTest.php \
  tests/Feature/LocationGovernance/ProfileResidenceTransferTest.php \
  tests/Feature/LocationGovernance/NonResidenceRelationshipVotingTest.php \
  tests/Feature/LocationGovernance/MultidimensionalMembershipTest.php \
  tests/Feature/LocationGovernance/GroupExplosionPreventionTest.php \
  tests/Feature/LocationGovernance/OfficialElectionGovernanceTopologyTest.php \
  tests/Feature/LocationGovernance/CommunityElectionBoundaryTest.php \
  tests/Feature/LocationGovernance/LocationLifecycleHistoryTest.php
```

Run the repository Full Validation workflow after this focused gate. A green focused gate alone is not sufficient to declare the candidate ready.
