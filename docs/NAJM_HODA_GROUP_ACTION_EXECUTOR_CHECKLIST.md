# Najm Hoda Group Action Executor Checklist

## Phase 1: Core (In Progress)
- [x] Define explicit command-driven action model (no hidden auto-action)
- [x] Add private-message action path (direct/request)
- [x] Add structured group action executor in service
- [x] Add policy guardrails for action execution
- [x] Add per-action access control by requester role
- [x] Add full action logging context for auditability

## Phase 2: Action Coverage
- [x] Create group post by command
- [x] Create group poll by command
- [x] Create comment under a post by command
- [x] React to message by command
- [x] React to post/comment by command

## Phase 3: Policy & Safety
- [x] Add action policy defaults in config
- [x] Add group-level override policy via `najm_hoda_group_configs.policy`
- [x] Add action rate limiting (`max_actions_per_hour`)
- [x] Add strict target validation (same group scope)
- [x] Add clear error replies for denied/invalid actions

## Phase 4: Management UX
- [ ] Surface action policy in group manager panel
- [ ] Surface action policy in admin Najm Hoda settings
- [ ] Add action executor status widget (enabled, last actions, failures)

## Phase 5: Reliability
- [ ] Add feature tests for each action command
- [ ] Add policy/authorization test matrix
- [ ] Add regression tests for malformed commands
- [ ] Add observability checks for failed actions

## Phase 6: Governance Alignment
- [ ] Add principle-aware action hints (justice, participation, transparency)
- [x] Add optional “propose-before-execute” mode for sensitive actions
- [ ] Add report export for group managers/inspectors
