# ماتریس شکاف نجم هُدا نسبت به ستارهٔ شمالی — V1

این سند فاصلهٔ محصول فعلی تا `NAJM_HODA_NORTH_STAR_PRODUCT_CONSTITUTION_V1.fa.md` را به backlog اجرایی تبدیل می‌کند. ارزیابی بر پایهٔ معماری و اسناد موجود در شاخهٔ hardening انجام شده و هر مورد باید در زمان پیاده‌سازی با کد و تست دوباره راستی‌آزمایی شود.

## مقیاس
- **A — موجود/بالغ:** زیرساخت اصلی وجود دارد؛ بیشتر نیازمند تکمیل و پوشش است.
- **B — موجود/ناقص:** بخش قابل اتکا وجود دارد ولی North Star را کامل پوشش نمی‌دهد.
- **C — ابتدایی:** نشانه یا پیاده‌سازی محدود وجود دارد.
- **D — مفقود/نیازمند طراحی:** قابلیت باید به‌صورت محصولی ساخته شود.

## ماتریس

| ID | قابلیت North Star | وضعیت | شواهد/وضع موجود | شکاف اصلی | اولویت |
|---|---|---|---|---|---|
| NS-01 | Policy, safety, audit, bounded execution | A | hardening، policy gate، readiness، execution boundaries | تبدیل قواعد Constitution به policy invariantهای صریح | P0 |
| NS-02 | Global Companion Widget | C | مسیرهای chat/context موجودند | حضور شناور سراسری، continuity و UX واحد وجود ندارد | P0 |
| NS-03 | Anonymous Welcome Companion | D | Welcome/registration سنتی موجود است | hesitation signals، شروع محترمانه، guest conversation، guided registration | P0 |
| NS-04 | Guest → Member conversation continuity | D | — | identity handoff امن و attach حافظه/گفتگو | P0 |
| NS-05 | Page/Role/Group/Project Context | B | Context و event work موجود است | `CognitiveContext` استاندارد و providerهای کامل | P0 |
| NS-06 | Personal Long-Term Memory | C | memoryهای محدود/گروهی وجود دارند | typed memory، provenance، confidence، conflict، forgetting، privacy | P0 |
| NS-07 | Group/Institutional Memory | B | group assistant/memory و event history | consolidation و retrieval معنایی یکپارچه | P1 |
| NS-08 | Founder/Strategic Context | D | اسناد و conversationها پراکنده‌اند | مدل رسمی decision/preference/constraint/priority | P1 |
| NS-09 | Knowledge Graph / World Model | C | roadmap آن را هدف گرفته | entity resolution، relations، temporal/provenance graph | P1 |
| NS-10 | Semantic Intent Understanding | C | orchestrator/agents موجود | عبور از keyword routing به intent/context/goal semantics | P1 |
| NS-11 | Goal & Commitment Engine | B | autonomous goal loop وجود دارد | persistent goals، dependency، conflict، success criteria، commitments | P1 |
| NS-12 | Planner v2 / execution DAG | C | orchestration و capability registry پایه | decomposition، replanning، cost/risk، simulation | P1 |
| NS-13 | User delegated actions | B | executor/policy foundation | post/poll/proposal/project/comment/message و consent UX | P0/P1 |
| NS-14 | Publish-as-user provenance | D | — | author=user، performed_by=najm_hoda، approved_by=user | P0 |
| NS-15 | Group Manager Copilot | C | group/admin foundations | agenda، meetings، minutes، resolutions، follow-up، coaching | P1 |
| NS-16 | Inspector Copilot | D | governance concepts موجود | evidence gathering، deviation checks، reports، independence guardrails | P2 |
| NS-17 | Management Skill Coaching | D | — | private skill model، evidence-based coaching، no public ranking | P2 |
| NS-18 | Election Intelligence | D | election/governance domain موجود | explainable suitability recommendation، conflict/bias policy | P2 |
| NS-19 | Proactive Assistance | C | monitoring/triage exists | user-facing interruption policy، relevance/urgency/confidence scoring | P1 |
| NS-20 | EarthCoop Operating Minister | B | health/triage/playbooks/readiness | unified executive briefing، goal-gap detection، cross-domain investigation | P1 |
| NS-21 | Repository-aware Technical Steward | B | CodeOps/hardening/Architect foundation | persistent repo model، feedback→issue→branch→patch→PR loop | P1 |
| NS-22 | Human-only final merge | A | current branch/PR workflow aligns | enforce as protected policy/invariant | P0 |
| NS-23 | Feedback → Engineering Loop | D | feedback and CodeOps separate | semantic clustering، impact estimation، code linkage، proposed fix | P2 |
| NS-24 | Model Gateway | C | AI provider abstractions/config exist | task-based model routing، verification pass، budgets/fallback | P1 |
| NS-25 | Constitution-as-code | D | North Star document exists | machine-readable invariants، policy mapping، CI checks | P0 |
| NS-26 | Privacy-scoped knowledge | C | auth/resource boundaries strengthened | memory scopes، purpose limitation، cross-user disclosure guard | P0 |
| NS-27 | Explainable Decision Trace | B | trace/audit foundations | standardized decision record across reasoning/planning/execution | P0 |
| NS-28 | Outcome Evaluation / Learning | C | operational metrics/playbooks | action→expected→observed→outcome و procedural learning | P2 |
| NS-29 | Controlled Self-Extension | C | Architect/CodeOps exist | capability gap→spec→sandbox→tests→PR→approval loop | P2 |
| NS-30 | Najm Bahar authority boundary | A | ledger/financial architecture and execution boundaries | encode economic invariants into policy/tests | P0 |
| NS-31 | 24/7 bounded autonomy | B | readiness, scheduler/queue/governance groundwork | autonomy per capability + live evidence + progressive rollout | P3 |

## شکاف‌های بحرانی P0

### G0-1 — Constitution as Code
North Star نباید فقط Markdown بماند. باید invariantهای ماشین‌خوانا برای authority، privacy، finance، code merge، user impersonation و audit تعریف شوند.

### G0-2 — Cognitive Context Contract
تمام invocationهای مهم باید `CognitiveContext` استاندارد داشته باشند: actor، role، page، group/project، permissions، goals، relevant memories، trace و data scopes.

### G0-3 — Companion Shell
یک shell سراسری برای نجم هُدا لازم است تا تجربهٔ کاربر از Welcome تا Home، Group، Project و Governance پیوسته باشد.

### G0-4 — Memory Foundation
بدون حافظهٔ typed و scope-aware، همراهی بلندمدت و Knowledge Graph قابل اعتماد نیست.

### G0-5 — Delegated Action Provenance
هر عمل به نام کاربر باید consent و provenance صریح داشته باشد و UI تأیید با backend audit هم‌معنا باشد.

## وابستگی‌ها
```text
Constitution-as-Code
        ↓
Cognitive Context ─────→ Companion Shell
        ↓                    ↓
Memory Foundation ─────→ Delegated Actions
        ↓                    ↓
Knowledge Graph ───────→ Group/Manager Copilot
        ↓                    ↓
Goal Engine ───────────→ Proactive Companion
        ↓
Planner v2 ────────────→ Operating Minister / Technical Steward
        ↓
Outcome Learning ──────→ Controlled Self-Extension
```

## معیار عبور از Foundation
Foundation زمانی کامل است که:
1. هر درخواست/رویداد مهم context و trace استاندارد داشته باشد.
2. هیچ capability حساس خارج از policy matrix قابل اجرا نباشد.
3. حافظه دارای provenance/scope/confidence و حق تصحیح/فراموشی باشد.
4. Companion در چند صفحهٔ اصلی context را بدون شکستن continuity حفظ کند.
5. یک اقدام واقعی کاربر از draft تا approval و execution با provenance کامل end-to-end کار کند.
6. invariantهای مالی و merge انسانی با تست خودکار محافظت شوند.

## نتیجه
بزرگ‌ترین فاصلهٔ محصول فعلی «توان اجرای backend» نیست؛ آن بخش نسبتاً پیشرفته است. شکاف اصلی در تبدیل این توان به یک **همراه شناختی پیوسته، حافظه‌دار، context-aware و دارای نمایندگی امن از انسان** است. بنابراین توسعهٔ بعدی باید ابتدا Foundation شناختی و Companion را تکمیل کند و سپس autonomy، governance intelligence و self-extension را روی آن بنا کند.
