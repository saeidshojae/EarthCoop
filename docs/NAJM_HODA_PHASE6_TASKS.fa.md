# ریزتسک های فاز 6 (Universal Autonomy Integration)

## هدف
- تکمیل تبدیل نجم هدا به لایه هوشمند سراسری پروژه.
- پوشش کامل مشاهده، تصمیم، و اقدام روی همه ماژول ها با کنترل ایمنی، ممیزی، و اختیار مرحله ای.
- نزدیک شدن به مدل «مدیر خودکار 24/7» بدون خروج از چارچوب Governance.

## لیست تسک ها

1. `P6-T01` Full Event Coverage Audit + Instrumentation
- شرح: ممیزی پوشش رویدادها در تمام ماژول های اصلی (chat, groups, support, economy, content, auth, admin) و افزودن instrumentation برای نقاط کور.
- خروجی: ماتریس پوشش رویداد + لیست gap + حداقل 95% پوشش مسیرهای بحرانی.
- وابستگی: اتمام فاز 5
- وضعیت: `done`
- پیشرفت:
  - `done`: Baseline v0 (`docs/NAJM_HODA_PHASE6_T01_EVENT_COVERAGE_BASELINE.fa.md`)
  - `done`: Event Contract v1 (`docs/NAJM_HODA_EVENT_CONTRACT_V1.fa.md`)
  - `done`: Domain Coverage Matrix v1 (`docs/NAJM_HODA_PHASE6_DOMAIN_EVENT_MATRIX.fa.md`)
  - `done`: RuntimeEventBus envelope normalization (`request_id`, `correlation_id`, `actor_id`, `scope`, `risk`, `event_version`, `emitted_at`)
  - `done`: instrumentation دامنه `support/tickets` با observer-based events (ticket created/status/assignment/comment)
  - `done`: instrumentation اولیه دامنه `najm-bahar` (transaction/scheduled-transaction/investment create+status)
  - `done`: پوشش مدل های اصلی نجم بهار با observer عمومی (account/sub-account/ledger/fee/salary-rule/salary-run/salary-run-item/project/review/category)
  - `done`: hookهای سرویس-محور نجم بهار در `transaction/salary/project` (requested/succeeded/failed/rejected/idempotent-hit)
  - `done`: تکمیل hook سرویس-محور برای `sub-account/fee/investment` (requested/succeeded/failed/rejected)
  - `done`: اتصال مستقیم eventهای سرویسی نجم بهار به policy/escalation engine (safety block + governance alert + approval request)
  - `done`: گسترش policy-escalation linkage برای دامنه های `support/auth` روی eventهای سرویس-محور (chat-assignment/triage/email-integration/google-login)
  - `done`: شروع policy-escalation linkage دامنه `content` با observer رویدادهای mutation (page/blog/kb-article/faq) + escalation برای `deleted`
  - `done`: تکمیل auth lifecycle با listener رویدادهای framework (`Login/Failed/Logout/Registered/PasswordReset`) + سرویس/کنترلر (`login/google_oauth/password_reset/password_change`)
  - `done`: تکمیل failure/rejected مسیر content lifecycle در `Admin\\PageController` و `Admin\\KbArticleController` (requested/succeeded/failed/rejected)
  - `done`: الگوی onboarding ماژول جدید داخل فاز 6 عملیاتی شد (`najm-hoda:onboarding-audit` + سرویس audit + چک لیست دستی)
  - `done`: پیاده سازی سنجش KPI پوشش بحرانی (service + artisan command) برای `CriticalPathCoverage`, `MandatoryFieldCompleteness`, `UnknownScopeRatio`, `UnknownRiskRatio`
  - `done`: فعال سازی probe استاندارد پوشش (`najm-hoda:coverage-probe` + گزینه `--probe` در `najm-hoda:coverage-kpi`) برای مشاهده KPI در همان execution
  - `done`: اضافه شدن شاخص پایداری (`sustainment`) با قاعده `N` اجرای متوالی `ok` بدون probe + گزینه `--require-sustained`
  - `done`: تغذیه non-probe critical-family با `coverage-heartbeat` + گزینه `--heartbeat` در KPI command و دستیابی به `sustained_ok=true` (3/3)
  - `done`: اعتبارسنجی پایداری در سطح زمانبندی (`hourly/daily`) با گیت خودکار `--require-sustained` (heartbeat-assisted + organic check)
  - `done`: مسیر `P6-T01` به سطح قابل تحویل رسید (coverage + KPI + sustainment + onboarding audit)

2. `P6-T02` Unified Domain Knowledge Graph
- شرح: ساخت گراف دانش بین دامنه ای (کاربر، گروه، پروژه، رخداد، اقدام، نتیجه) برای استدلال پیوسته و تصمیمات چندمرحله ای.
- خروجی: سرویس graph-query با scope بندی RBAC و traceable context assembly.
- وابستگی: `P6-T01`
- وضعیت: `done`
- پیشرفت:
  - `done`: پیاده سازی سرویس `NajmHodaUnifiedDomainKnowledgeGraphService` با query بین دامنه ای (`users/groups/projects/tickets/runtime_signals`)
  - `done`: پیاده سازی RBAC scope resolution (`global|actor|group:ID`) با کاهش خودکار scope برای actor غیرادمین
  - `done`: traceable context assembly (`trace_id`, `requested_scope`, `effective_scope`, `scope_reduced_by_rbac`, `data_sources`)
  - `done`: اضافه شدن command اجرایی `najm-hoda:graph-query`
  - `done`: سخت‌سازی query profileها (`overview/member_support/project_delivery/ops_triage`) با domain shaping و scope filter اختصاصی
  - `done`: enrich شدن runtime signal schema (`correlation/request/actor/entity refs/outcome`) برای ردیابی چندمرحله‌ای
  - `done`: enrich شدن edge semantics (entity-impact + correlation edges + ops-domain signalization)
  - `done`: اضافه شدن query patternهای تصمیمی (multi-hop traversal/use-case templates) برای `support escalation / project risk hotspot / ops alert chains`

3. `P6-T03` Multi-Horizon Goal Engine
- شرح: موتور هدف گذاری خودکار در افق روزانه/هفتگی/ماهانه بر پایه KPI/SLO و state واقعی سیستم.
- خروجی: تولید/اولویت بندی backlog خودکار + چرخه بازبینی هدف ها.
- وابستگی: `P6-T02`
- وضعیت: `done`
- پیشرفت:
  - `done`: پیاده سازی سرویس `NajmHodaMultiHorizonGoalEngineService` با ورودی KPI+Graph و خروجی `daily/weekly/monthly + prioritized backlog`
  - `done`: تعریف الگوهای backlog عملیاتی بر اساس KPI breach و patternهای گراف (`support escalation`, `project hotspot`, `ops alert chain`)
  - `done`: اضافه شدن command عملیاتی `najm-hoda:multi-goals` + زمانبندی دوره‌ای در `Kernel`
  - `done`: پیاده سازی چرخه بازبینی هدف با `NajmHodaMultiHorizonGoalReviewService` و command `najm-hoda:multi-goals-review` (trend: improving/stable/regressing)
  - `done`: هم‌راستاسازی اولیه خروجی backlog و trend review با ارکستریتور بین‌ماژولی فاز بعد (`P6-T04`) برای اجرای زنجیره‌ای اقدام‌ها

4. `P6-T04` Cross-Module Capability Orchestrator
- شرح: ارکستریتور اقدام بین ماژولی با قرارداد قابلیت ها، پیش شرط/پس شرط، و policy gate.
- خروجی: اجرای زنجیره اقدام های ترکیبی با rollback گام به گام.
- وابستگی: `P6-T02`, `P6-T03`
- وضعیت: `done`
- پیشرفت:
  - `done`: پیاده سازی سرویس `NajmHodaCrossModuleCapabilityOrchestratorService` برای اجرای chain چندمرحله‌ای با capability contract + safety gate
  - `done`: افزودن rollback گام‌به‌گام در شکست chain با اجرای rollback actionهای واقعی (`rollback_ops_monitor`, `rollback_engagement_recommendations`) در apply-mode
  - `done`: افزودن command اجرایی `najm-hoda:orchestrate --from-multi-goals` + زمانبندی دوره‌ای
  - `done`: توسعه post-condition check (`executor_intent_recorded`) برای stepهای اجرایی و fail-fast با rollback
  - `done`: توسعه rollback از سطح action-intent به compensating transaction برای ماژول‌های stateful (`ticket_status_revert`, `project_status_revert`) با fallback کنترل‌شده به capability rollback

5. `P6-T05` Autonomy Permissioning v2 (Fine-Grained Delegation)
- شرح: مدل تفویض اختیار دقیق برای کاربر/گروه/نقش/عمل با expiration و approval policy.
- خروجی: delegated permissions قابل ممیزی + کاهش ریسک اقدام خارج از مجوز.
- وابستگی: `P6-T04`
- وضعیت: `in_progress`
- پیشرفت:
  - `done`: پیاده سازی سرویس `NajmHodaDelegatedPermissionService` (grant/revoke/authorize/listActive) با retention/expiry و audit events
  - `done`: اضافه شدن commandهای عملیاتی `najm-hoda:delegation-grant` و `najm-hoda:delegation-audit`
  - `done`: اتصال enforcement تفویض اختیار به orchestrator در apply-mode (قابل فعال سازی با `permissioning_v2.enforce_apply_requires_delegation`)
  - `done`: مسیر escalation برای delegation requiring approval از طریق `NajmHodaAutonomyApprovalService`
  - `done`: تکمیل delegation در سطح نقش/گروه برای سناریوهای واقعی با `context-aware authorization` (پشتیبانی از `role_*` و `group_*` در context)
  - `done`: عبور context نقش/گروه از orchestrator به permission check در apply-mode
  - `done`: تکمیل تست رگرسیون role/group delegation و orchestration context propagation
  - `in_progress`: اتصال کامل delegation audit/explainability به UI نظارتی (`P6-T07`)

6. `P6-T06` Adaptive Safety + Policy Learning Loop
- شرح: حلقه بازتنظیم policy بر اساس drift، false positive، failures و postmortem ها.
- خروجی: policy tuning کنترل شده با شواهد پیش و پس از تغییر.
- وابستگی: `P6-T03`, `P6-T04`
- وضعیت: `pending`

7. `P6-T07` Human Oversight Console v2
- شرح: کنسول تصمیم با explainability کامل (چرا/با چه داده ای/با چه ریسکی)، veto سریع، و مسیر override.
- خروجی: تصمیمات قابل دفاع مدیریتی + کاهش تصمیمات مبهم AI.
- وابستگی: `P6-T04`, `P6-T05`
- وضعیت: `in_progress`
- پیشرفت:
  - `done`: پیاده سازی سرویس snapshot کنسول نظارتی (`NajmHodaOversightConsoleService`) با تجمیع approvals/controls/audit/delegation/events
  - `done`: اضافه شدن endpoint خواندنی کنسول نظارتی (`/admin/najm-hoda/autonomy/oversight/console`)
  - `done`: اضافه شدن مسیر `quick veto` برای approval (`/admin/najm-hoda/autonomy/approvals/{approvalId}/veto`)
  - `done`: اضافه شدن command عملیاتی `najm-hoda:oversight-console` + زمانبندی ساعتی snapshot
  - `in_progress`: اتصال UI مدیریتی explainability/veto/override روی داشبورد autonomy

8. `P6-T08` Safe CodeOps Expansion (Canary + Auto Rollback)
- شرح: گسترش CodeOps به rollout تدریجی (canary) و rollback خودکار مبتنی بر SLO breach.
- خروجی: استقرار کم ریسک اصلاحات خودکار در محیط واقعی.
- وابستگی: `P6-T04`, `P6-T06`
- وضعیت: `pending`

9. `P6-T09` Continuous Evaluation Harness
- شرح: ارزیابی شبانه end-to-end روی کیفیت تصمیم، safety regression، و نرخ انحراف.
- خروجی: گزارش دوره ای کیفیت خودگردانی + هشدار خودکار افت عملکرد.
- وابستگی: `P6-T06`, `P6-T07`
- وضعیت: `pending`

10. `P6-T10` 24/7 Operational Autonomy Activation
- شرح: فعال سازی حالت عملیات 24/7 با شیفت مجازی، escalations، و runbook execution واقعی.
- خروجی: مدیریت خودکار شبانه پایدار با threshold های توقف ایمن.
- وابستگی: `P6-T08`, `P6-T09`
- وضعیت: `pending`

11. `P6-T11` Shadow-to-Live Rollout Strategy
- شرح: گذار کنترل شده از Shadow Mode به Live Mode با guardrail های مرحله ای.
- خروجی: پلن rollout مرحله ای + معیار عبور هر مرحله.
- وابستگی: `P6-T10`
- وضعیت: `pending`

12. `P6-T12` Phase-6 Go/No-Go + Executive Sign-off
- شرح: جمع بندی نهایی ریسک/کیفیت/پایداری و تصمیم Go/No-Go برای بهره برداری سطح 6.
- خروجی: تایید مدیریتی مبتنی بر شواهد فنی و عملیاتی.
- وابستگی: `P6-T01` تا `P6-T11`
- وضعیت: `pending`

## معیار اتمام فاز 6
- حداقل 95% مسیرهای بحرانی سیستم تحت پوشش رویداد و ردیابی تصمیم باشند.
- حداقل 70% عملیات روزمره قابل اجرای خودکار کنترل شده باشد.
- نرخ خطای بحرانی خودگردانی کمتر از 1% در پنجره 30 روزه.
- تمام اقدام های خودکار دارای explainability و audit trail کامل باشند.
- گذار Shadow-to-Live بدون رخداد بحرانی تایید شود.
