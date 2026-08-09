# Backlog اجرایی نجم هُدا — V1

مرجع: North Star + Gap Matrix + Roadmap V2.

## Epic NH-000 — Constitutional Foundation
- [ ] NH-001 تعریف schema ماشین‌خوانای Capability Authority Matrix
- [ ] NH-002 تعریف Autonomy Level per capability
- [ ] NH-003 تعریف Human Approval invariant برای protected merge/deploy حساس
- [ ] NH-004 کدنویسی Najm Bahar financial authority invariants
- [ ] NH-005 تعریف privacy/purpose scopes
- [ ] NH-006 استاندارد Decision Trace
- [ ] NH-007 تست red-lineها در CI
- [ ] NH-008 مستندسازی mapping هر invariant به بند North Star

## Epic NH-100 — Cognitive Context
- [ ] NH-101 طراحی `CognitiveContext` DTO/contract
- [ ] NH-102 `ContextProviderInterface`
- [ ] NH-103 UserContextProvider
- [ ] NH-104 PageContextProvider
- [ ] NH-105 RoleContextProvider
- [ ] NH-106 GroupContextProvider
- [ ] NH-107 ProjectContextProvider
- [ ] NH-108 SystemContextProvider
- [ ] NH-109 NajmBaharContextProvider
- [ ] NH-110 GoalContextProvider
- [ ] NH-111 MemoryContextProvider placeholder
- [ ] NH-112 Context budget/ranker
- [ ] NH-113 permission-aware filtering
- [ ] NH-114 context snapshot in trace
- [ ] NH-115 cross-user/group isolation tests
- [ ] NH-116 Orchestrator integration behind feature flag

## Epic NH-200 — Companion Shell
- [ ] NH-201 inventory chat UI/API موجود
- [ ] NH-202 Global floating widget shell
- [ ] NH-203 page-context bridge frontend→backend
- [ ] NH-204 guest conversation identity
- [ ] NH-205 conversation continuity across navigation
- [ ] NH-206 Welcome introduction flow
- [ ] NH-207 hesitation/help signal collector با privacy minimization
- [ ] NH-208 proactive invitation policy
- [ ] NH-209 guided registration actions
- [ ] NH-210 secure guest→member handoff
- [ ] NH-211 Home continuity
- [ ] NH-212 Group continuity
- [ ] NH-213 Project continuity
- [ ] NH-214 E2E Companion v1 scenario

## Epic NH-300 — Memory Foundation
- [ ] NH-301 memory domain model/types
- [ ] NH-302 migration/schema
- [ ] NH-303 provenance/confidence/importance/sensitivity metadata
- [ ] NH-304 MemoryExtractor
- [ ] NH-305 MemoryRetriever
- [ ] NH-306 MemoryRanker
- [ ] NH-307 MemoryConsolidator
- [ ] NH-308 conflict/supersede resolver
- [ ] NH-309 decay/expiry
- [ ] NH-310 correction/forget workflow
- [ ] NH-311 privacy scope enforcement
- [ ] NH-312 personal memory
- [ ] NH-313 group memory integration
- [ ] NH-314 founder/strategic memory
- [ ] NH-315 memory explanation/provenance API
- [ ] NH-316 E2E recall + correction + isolation tests

## Epic NH-400 — Delegated User Actions
- [ ] NH-401 standard delegated-action envelope
- [ ] NH-402 consent/approval contract
- [ ] NH-403 provenance fields: author/performed_by/approved_by
- [ ] NH-404 post draft/publish capability
- [ ] NH-405 poll capability
- [ ] NH-406 proposal capability
- [ ] NH-407 project draft capability
- [ ] NH-408 preview/dry-run UX
- [ ] NH-409 action audit
- [ ] NH-410 rollback/compensation where possible
- [ ] NH-411 E2E idea→consult→draft→approve→publish

## Epic NH-500 — Knowledge Graph
- [ ] NH-501 graph schema
- [ ] NH-502 entity resolution
- [ ] NH-503 relation extraction
- [ ] NH-504 temporal/provenance model
- [ ] NH-505 memory→graph consolidation
- [ ] NH-506 event→graph update
- [ ] NH-507 permission-aware query
- [ ] NH-508 graph→context adapter

## Epic NH-600 — Goals & Planner
- [ ] NH-601 persistent Goal model
- [ ] NH-602 Commitment model
- [ ] NH-603 success criteria/deadlines/dependencies
- [ ] NH-604 semantic intent layer
- [ ] NH-605 goal decomposition
- [ ] NH-606 capability selection
- [ ] NH-607 execution DAG
- [ ] NH-608 risk/cost estimator
- [ ] NH-609 fallback/replanning
- [ ] NH-610 sensitive-plan simulation
- [ ] NH-611 planner audit

## Epic NH-700 — Governance Companion
- [ ] NH-701 manager role context
- [ ] NH-702 meeting scheduler capability
- [ ] NH-703 agenda assistant
- [ ] NH-704 minutes assistant
- [ ] NH-705 resolution/action extraction
- [ ] NH-706 follow-up tracker
- [ ] NH-707 management briefing
- [ ] NH-708 private coaching evidence model
- [ ] NH-709 inspector context
- [ ] NH-710 inspector evidence/report workflow

## Epic NH-800 — Election Intelligence
- [ ] NH-801 role competency schema
- [ ] NH-802 evidence aggregation
- [ ] NH-803 conflict-of-interest rules
- [ ] NH-804 protected-attribute exclusion policy
- [ ] NH-805 explainable recommendation
- [ ] NH-806 uncertainty representation
- [ ] NH-807 anti-persuasion/no-coercion tests

## Epic NH-900 — Proactive Companion
- [ ] NH-901 proactive signal model
- [ ] NH-902 urgency/importance/confidence/actionability/interruption scoring
- [ ] NH-903 user proactivity preferences
- [ ] NH-904 quiet mode
- [ ] NH-905 unresolved commitment detection
- [ ] NH-906 forgotten goal detection
- [ ] NH-907 anomaly/opportunity surfacing
- [ ] NH-908 daily/weekly briefing

## Epic NH-1000 — Operating Minister
- [ ] NH-1001 executive world-state aggregator
- [ ] NH-1002 goal-gap detector
- [ ] NH-1003 cross-domain investigation planner
- [ ] NH-1004 executive priority ranking
- [ ] NH-1005 decision/commitment follow-up
- [ ] NH-1006 escalation boundary tests

## Epic NH-1100 — Technical Steward
- [ ] NH-1101 repository world model
- [ ] NH-1102 runtime→code linkage
- [ ] NH-1103 feedback semantic clustering
- [ ] NH-1104 impact estimation
- [ ] NH-1105 isolated branch workflow
- [ ] NH-1106 patch/test/static/security pipeline
- [ ] NH-1107 self-diff review
- [ ] NH-1108 commit/push/draft PR
- [ ] NH-1109 protected merge human-only enforcement
- [ ] NH-1110 post-deploy outcome observation

## Epic NH-1200 — Model Gateway
- [ ] NH-1201 model/provider interface
- [ ] NH-1202 task-based routing
- [ ] NH-1203 cost/latency/quality budgets
- [ ] NH-1204 sensitive verification pass
- [ ] NH-1205 fallback policy
- [ ] NH-1206 telemetry/evaluation

## Epic NH-1300 — Outcome Learning
- [ ] NH-1301 ActionOutcome model
- [ ] NH-1302 expected/observed linkage
- [ ] NH-1303 delayed evaluator
- [ ] NH-1304 human correction ingestion
- [ ] NH-1305 playbook scoring
- [ ] NH-1306 failed-plan clustering
- [ ] NH-1307 procedural memory update

## Epic NH-1400 — Controlled Self-Extension
- [ ] NH-1401 capability gap detector
- [ ] NH-1402 specification generator
- [ ] NH-1403 sandbox workspace
- [ ] NH-1404 implementation/test generator
- [ ] NH-1405 static/security validation
- [ ] NH-1406 capability evaluation
- [ ] NH-1407 draft PR generation
- [ ] NH-1408 human approval gate
- [ ] NH-1409 capability registration after acceptance

## اولین Slice اجرایی
ترتیب شروع:
1. NH-001 تا NH-008
2. NH-101 تا NH-116
3. NH-201 تا NH-214
4. NH-301 تا NH-316
5. NH-401 تا NH-411

این Slice باید قبل از ورود جدی به Knowledge Graph و Planner v2 یک تجربهٔ end-to-end واقعی از Companion حافظه‌دار و عامل بسازد.
