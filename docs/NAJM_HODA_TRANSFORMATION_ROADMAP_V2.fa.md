# نقشهٔ راه تحول نجم هُدا — V2

**مرجع حاکم:** `NAJM_HODA_NORTH_STAR_PRODUCT_CONSTITUTION_V1.fa.md`  
**ورودی اجرایی:** `NAJM_HODA_NORTH_STAR_GAP_MATRIX_V1.fa.md`

## اصل برنامه
از این نسخه به بعد توسعهٔ نجم هُدا feature-driven نیست؛ **North-Star-driven** است. هر epic باید به بندهای Constitution و Gap IDها قابل ردیابی باشد.

## Phase 0 — Constitutional Foundation
هدف: تبدیل اصول بنیادین به محدودیت‌های واقعی سیستم.

- machine-readable capability/autonomy matrix
- authority scopes و human-approval boundaries
- finance invariants برای Najm Bahar
- protected-code merge invariant
- privacy/purpose scopes
- standard Decision Trace schema
- CI tests برای red lines

**Exit:** نقض خطوط قرمز اصلی با policy/test مسدود شود.

## Phase 1 — Cognitive Context Engine
هدف: نجم هُدا در هر invocation بداند «چه کسی، کجا، با چه نقش و اختیاری، دربارهٔ چه چیزی» حضور دارد.

- `CognitiveContext`
- `ContextProviderInterface`
- User/Page/Role/Group/Project/System/NajmBahar/Goal/Memory providers
- context budget/ranking
- permission-aware filtering
- context snapshot + trace
- isolation tests

**Exit:** تمام entry pointهای اصلی context استاندارد دریافت کنند.

## Phase 2 — Companion Foundation
هدف: نجم هُدا از backend به حضور واقعی در محصول تبدیل شود.

- Global floating widget
- conversation shell مشترک
- page-context bridge
- guest session
- respectful proactive trigger framework
- Welcome introduction
- guided onboarding/registration
- guest→member secure continuity
- Home/Group/Project continuity

**Milestone:** `Najm Hoda Companion v1`.

## Phase 3 — Memory Engine
هدف: همراهی بلندمدت و قابل اعتماد.

- episodic/semantic/preference/relationship/commitment/procedural memory
- provenance/confidence/importance/sensitivity/validity
- extractor/retriever/ranker/consolidator
- conflict/supersede
- decay/forget/correction
- privacy scopes
- personal/group/founder memory
- memory explanation

**Exit:** نجم هُدا بتواند یک تصمیم/ترجیح گذشته را با منشأ درست بازیابی و توضیح دهد.

## Phase 4 — Guided User Actions
هدف: نجم هُدا مباشر واقعی ولی پاسخگو شود.

- draft/publish post
- poll
- proposal
- project draft
- comment/message where permitted
- explicit consent UX
- `author / performed_by / approved_by`
- dry-run/preview
- rollback where possible
- action audit

**Exit:** ایدهٔ کاربر در گروه → مشورت → draft → approval → انتشار از حساب کاربر end-to-end.

## Phase 5 — Knowledge Graph / World Model
هدف: تبدیل حافظهٔ پراکنده به شناخت روابط.

- Person/Group/Project/Goal/Decision/Event/Capability/Policy/Risk nodes
- entity resolution
- temporal relations
- provenance/confidence
- memory→graph consolidation
- event→graph updates
- permission-aware graph query
- graph→context

## Phase 6 — Goal, Commitment & Planner v2
هدف: عبور از پاسخ‌گویی به مأموریت‌محوری.

- persistent goals
- desired state/success criteria
- dependencies/risks/deadlines
- goal decomposition
- commitment tracking
- semantic intent
- capability selection
- execution DAG
- cost/risk estimation
- replanning/fallback
- simulation for sensitive plans

## Phase 7 — Group Governance Companion
هدف: نجم هُدا وزیر و مربی مدیر/بازرس باشد.

- manager context
- meeting scheduling
- agenda
- minutes
- resolution/action extraction
- follow-up
- management briefing
- private skill coaching
- inspector evidence/report workflows
- governance-specific permission boundaries

## Phase 8 — Election Intelligence
هدف: کمک خردمندانه و غیرتحمیلی در انتخابات بدون نامزد.

- role competency model
- relevant evidence aggregation
- explainable recommendation
- conflict-of-interest checks
- bias/protected-attribute exclusion policy
- uncertainty display
- no coercion / no hidden persuasion
- auditability

## Phase 9 — Proactive Companion
هدف: نجم هُدا بدون مزاحمت نیاز واقعی را پیش از درخواست تشخیص دهد.

- hesitation/help signals
- unresolved commitments
- forgotten goals
- anomaly/opportunity detection
- urgency/importance/confidence/actionability/interruption-cost
- quiet mode
- user proactivity preferences
- daily/weekly briefings

## Phase 10 — EarthCoop Operating Minister
هدف: برای مدیریت کل، نجم هُدا وضعیت جهان عملیاتی EarthCoop را یکپارچه بفهمد.

- executive world-state briefing
- goal-gap detection
- cross-domain investigation
- operations/finance/governance/project signals
- incident prioritization
- recommendations vs autonomous low-risk action
- decision/commitment follow-up

## Phase 11 — Technical Steward & Feedback Engineering Loop
هدف: نگهداری و ارتقای مداوم codebase با تصمیم merge انسانی.

- persistent repository model
- runtime/feedback→code linkage
- feedback clustering
- issue hypothesis
- isolated branch
- patch
- tests/static/security checks
- self-review
- commit/push/draft PR
- human-only protected merge
- post-deploy outcome observation

## Phase 12 — Model Gateway & Verification
هدف: هویت نجم هُدا از provider جدا و استفاده از مدل‌ها هدفمند شود.

- task-based routing
- cost/latency/quality budgets
- strong-model verification for sensitive reasoning
- provider fallback
- model performance telemetry
- no provider-owned identity/memory

## Phase 13 — Outcome Learning
هدف: سیستم بفهمد آیا کارش واقعاً نتیجه داده است.

- expected outcome
- observed outcome
- delayed evaluation
- human correction
- playbook scoring
- failed-plan clustering
- procedural memory updates
- planner quality metrics

## Phase 14 — Controlled Self-Extension
هدف: تشخیص و ساخت قابلیت جدید بدون self-authority.

- capability gap detector
- specification generator
- sandbox implementation
- generated tests
- security/static checks
- evaluation
- draft PR
- human approval
- capability registration after acceptance

## Phase 15 — Bounded 24/7 Autonomy
هدف: autonomy عملیاتی گسترده ولی دامنه‌دار.

سطوح:
`L0 Observe → L1 Advise → L2 Prepare → L3 Low-risk Execute → L4 Managed Autonomous → L5 Mission Autonomous`

سطح autonomy به capability تعلق دارد، نه کل نجم هُدا.

## Milestoneهای محصولی
1. **Companion v1:** Welcome→Registration→Home→Group continuity.
2. **Trusted Memory v1:** حافظهٔ شخصی/گروهی با provenance و privacy.
3. **User Agent v1:** مشورت تا اقدام واقعی با consent.
4. **Governance Minister v1:** مدیریت جلسه/مصوبه/بازرسی.
5. **Operating Minister v1:** briefing و goal-gap برای مدیریت کل.
6. **Technical Steward v1:** feedback/problem→tested PR.
7. **Learning Hoda v1:** outcome-based procedural learning.
8. **Self-Extending Hoda v1:** capability→sandbox→PR.

## قاعدهٔ اولویت
هیچ Phase بالاتر نباید برای نمایش قابلیت جذاب، dependency شناختی/امنیتی پایین‌تر را دور بزند. در عین حال UI Companion زود ساخته می‌شود تا معماری از ابتدا با تجربهٔ واقعی انسان آزموده شود.
