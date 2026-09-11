# Location/Governance Permanent UAT Scenarios

These scenarios are the permanent acceptance matrix for the global Location/Governance architecture. Use test/UAT users and disposable test data only. No scenario authorizes production cutover or destructive production work.

| ID | Scenario | Expected result | Permanent regression |
|---|---|---|---|
| LG-UAT-01 | Urban Sari residence | Country → province → county → section → city is accepted as a canonical residence path; deeper urban region/neighborhood/street remains valid where present. | `RegistrationPrimaryResidenceTest`, `GlobalArchitectureScenarioTest` |
| LG-UAT-02 | Chahardangeh rural residence | Country → province → county → section → rural district → village resolves without requiring the urban branch. | `RegistrationPrimaryResidenceTest`, `GlobalArchitectureScenarioTest` |
| LG-UAT-03 | Village without neighborhood | Village remains a valid residence endpoint even when it has no neighborhood child. | `RegistrationPrimaryResidenceTest`, `GlobalArchitectureScenarioTest` |
| LG-UAT-04 | Urban neighborhood/street | Urban region → neighborhood → street follows the canonical schema graph and can be selected where endpoint rules allow. | `GlobalArchitectureScenarioTest`, `LocationSelectionApiTest` |
| LG-UAT-05 | Alley/complex/building Community | Micro-locations can exist without automatically becoming formal governance areas; complex/building Community creation remains policy-controlled and outside formal election topology by default. | `MicroLocationWithoutCommunityTest`, `CommunityAreaCreationTest`, `CommunityElectionBoundaryTest` |
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

## UAT execution rules

1. Use only dedicated test/UAT accounts and disposable or explicitly approved non-production data.
2. Record the exact branch SHA and Full Validation run ID used for the session.
3. For every scenario record pass/fail, unexpected UI behavior, and any generated proposal/import/governance identifiers needed for debugging.
4. Never convert a failed scenario into a manual database correction. Fix the code/data contract, rerun automated regression, then repeat UAT.
5. GPS remains optional; a tester must be able to complete canonical location selection manually.
6. Proposal verification must never be treated as administrative approval.
7. Community must not appear in formal upstream election topology unless a future explicitly approved architecture change says otherwise.
8. A successful C12 UAT candidate is still **not** permission for production cutover. Production cutover begins only after the separate C13 approval checkpoint.

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
