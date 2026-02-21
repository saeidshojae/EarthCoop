# baseline شاخص های حکمرانی نجم هدا (فاز 5)

## هدف
- تعریف رسمی KPI/SLO برای ارزیابی پایداری، کیفیت تصمیم، و ایمنی خودگردانی.

## شاخص ها

1. `auto_action_success_rate`
- تعریف: نسبت اقدامات خودکار موفق به کل اقدامات اجراشده.
- فرمول: `executed_success / (executed_success + executed_failed)`
- هدف: `>= 0.95`
- هشدار: `< 0.90`

2. `autonomy_coverage_rate`
- تعریف: سهم عملیات روزانه که به صورت خودکار انجام شده است.
- فرمول: `automated_daily_operations / total_daily_operations`
- هدف: `>= 0.60`
- هشدار: `< 0.50`

3. `mttr_reduction_rate`
- تعریف: درصد کاهش MTTR نسبت به baseline قبل از خودگردانی.
- فرمول: `(baseline_mttr - current_mttr) / baseline_mttr`
- هدف: `>= 0.30`
- هشدار: `< 0.20`

4. `rollback_unwanted_rate`
- تعریف: نسبت rollback ناخواسته به کل rollbackها.
- فرمول: `unwanted_rollbacks / total_rollbacks`
- هدف: `<= 0.02`
- هشدار: `> 0.03`

5. `user_satisfaction_score`
- تعریف: میانگین رضایت کاربران از تعامل با نجم هدا.
- فرمول: `avg_user_satisfaction_score`
- هدف: `>= 0.80`
- هشدار: `< 0.75`

6. `human_approval_latency_minutes`
- تعریف: میانگین زمان تصمیم تایید انسانی.
- فرمول: `avg(decision_at - requested_at)`
- هدف: `<= 30 دقیقه`
- هشدار: `> 45 دقیقه`

7. `policy_drift_rate`
- تعریف: نسبت انحراف تصمیم از policy مصوب.
- فرمول: `policy_drift_events / total_decisions`
- هدف: `<= 0.01`
- هشدار: `> 0.02`

## منبع حقیقت (Source of Truth)
- config رسمی:
  - `config/najm-hoda.php`
  - مسیر: `runtime.autonomy.governance.kpis.*`
- سرویس کاتالوگ:
  - `app/Services/NajmHoda/Runtime/NajmHodaGovernanceKpiCatalogService.php`
- endpoint مدیریتی:
  - `GET admin/najm-hoda/autonomy/governance/baseline`
