# Mobile membership payment: confirmed Dim and Active sources

## خلاصه برای بررسی سعید

- هر دو روش پرداخت، بهار کمرنگ و بهار فعال، در همین مرحله پشتیبانی می‌شوند.
- کمرنگ فقط از موجودی آزاد حساب اصلی برداشت می‌شود؛ کمرنگ متعهد مصرف نمی‌شود.
- برای فعال، حساب اصلی یا حساب فرعی خود کاربر انتخاب می‌شود و موجودی رزروشده کنار گذاشته می‌شود. مطابق منطق فعلی، یک حساب باید کل مبلغ را داشته باشد؛ تجمیع چند حساب یا ترکیب فعال و کمرنگ اضافه نمی‌شود.
- مبلغ و تقسیم آن از تنظیمات معتبر سرور می‌آیند؛ ۱۲ بهار و تقسیم ۶/۳/۳ پیش‌فرض‌اند، نه اعداد ثابت در اپ.
- کاربر پیش از ارسال، مبلغ، دوره، نوع موجودی و حساب برداشت را تأیید می‌کند. تغییر این شرایط در سرور باعث رد بدون برداشت و دریافت تأیید تازه می‌شود.
- قطع پاسخ، نتیجهٔ نامعلوم است. اپ وضعیت را بررسی می‌کند؛ تکرار همان قصد همان کلید را دارد و هیچ پرداختی پس از بازشدن دوبارهٔ اپ خودکار ارسال نمی‌شود.
- سازگاری سایت قدیمی حفظ می‌شود. موبایل جدید تا وقتی سرور قرارداد امن جدید را ارائه نکند، دکمهٔ پرداخت را فعال نمی‌کند.
- دو یافتهٔ بررسی کد، سهم صفر صندوق‌ها و حفظ کمرنگ متعهد در جمع حساب، پیش از قابلیت پرداخت با تست متمرکز بررسی و در صورت بازتولید اصلاح می‌شوند.

تأیید قبلی شما مبنای این مشخصات است و محدودیت «فقط کمرنگ» حذف شده است. ادامهٔ متن جزئیات فنی قرارداد و معیارهای آزمون است. این فایل برای مرور طرح نوشته شده؛ هنوز کد پرداخت تغییر نکرده است.

## Approved intent and boundaries

Saeed approved development of safe membership payment after Android+17 and explicitly corrected the initial dim-only suggestion: **both available Dim and Active Bahar must be supported according to existing server rules**. The existing fee/application services remain authoritative; do not invent new monetary rules. This is an architectural API/client contract extension, not an implementation-complete checkpoint. This written specification is submitted for review before an implementation plan.

Existing-account Android only. No wallet provisioning, general transfer, standalone participation activation, financial offline queue, automatic payment after restart, live charge, push activation, main merge or FTP publication. Google/network resilience remains deferred. The attached Najm Hoda document is unrelated and has not been read. Historical signed+17 stays unchanged.

## Source-grounded rules

References: NajmBaharMembershipFeeApplicationService, FeeService, MembershipFeeStatusService, AccountBalanceService, ActiveBaharReservationService, MonetaryService, TransactionService, API/V1/NajmBaharMembershipFeeController, the web membership controller and NajmBaharMembershipFeeContractTest.

1. All money is integer Gol; display uses existing exact formatting (100 Gol =1 Bahar). Current configured fee is authoritative;12 Bahar is only the default. The default split is6 operations/3 insurance/3 destruction, but configured nonnegative parts summing exactly to the fee remain supported.
2. Membership period is the server-derived Gregorian anniversary year, never the phone calendar.
3. Dim payment uses **available Dim of the main account**, never committed Dim or a sum across subaccounts. Exactly the fee is activated, then distributed atomically using the existing MonetaryService/TransactionService. No partial Dim payment.
4. Active payment uses one selected eligible source: the member's main account or an enabled subaccount owned by that main account. It must cover the entire fee after Active reservations. Active payment must not activate or consume Dim. Existing business flow does not combine multiple accounts or mix Dim and Active in one payment.
5. Existing legacy automatic Active selection prioritizes the main account, then the oldest eligible enabled subaccount. New mobile payment exposes an explicit source and must not silently move to another account when the selected source loses funds. This clarifies consent, while preserving old callers' existing fallback semantics.
6. Transfers to membership treasury funds remain system operations permitted before general user-to-user trading unlock. The app must not hard-code a user-count unlock threshold or alter it; TransactionService and configured policy remain authoritative.
7. Fee distribution and participation award remain in the same transaction; one membership payment per user/period. Existing legacy paid evidence stays recognized.

## API read extension

Extend authenticated GET /api/v1/najm-bahar/membership-fee additively with payment_contract_version=1 and payment_sources. Existing response fields remain unchanged for+17 and old web/API consumers. Old can_pay_from_active is an aggregate hint, not proof that any individual source can pay.

Each source row has kind(main/subaccount), sub_account_id(null/main or positive integer), account_number, name, active_available_gol, dim_available_gol and can_pay_active/can_pay_dim. Main Dim eligibility uses balance_faded and excludes committed_dim. Subaccount Dim payment is always ineligible for this contract. Active eligibility uses the existing owned-source rules and reservation-adjusted spendable funds. Omit disabled/foreign subaccounts. Main source is included when the existing wallet exists.

The projection must not call ensureSubAccountAccount or create/synchronize financial rows merely for listing. For an existing mirror, compute reservation deduction using its identity but the same balance authority used by the payment service; for a missing mirror use existing subaccount values with no reservation row for a nonexistent payer. Actual pay always rechecks funds and ownership under its existing locking boundaries. No availability projection guarantees future payment.

New mobile shows payment controls only when contract_version1 and a valid source list are present. A legacy server leaves read-only status available and shows that payment is not ready; never fall back to an unguarded legacy POST.

## POST consent extension and compatibility

Keep POST /api/v1/najm-bahar/membership-fee/pay and mandatory Idempotency-Key. Keep payment_source(dim/active) and sub_account_id. Add an optional expected object for backward compatibility; once supplied it must be complete and strictly validated:

- payment_year: positive integer;
- fee_gol: positive integer;
- breakdown: exactly operations_salary_gol, central_insurance_gol, money_destruction_gol, nonnegative integers summing to fee_gol;
- policy_version_id: present, nullable positive integer;
- account_number: nonempty string identifying the selected debit source.

No partial expected object or unexpected nested field is accepted. New mobile always supplies expected. Existing callers without it keep legacy semantics. New dim intent requires no sub_account_id and account_number equal to the member's main account. New active intent with null sub_account_id selects the main account strictly; with an ID it selects that owned enabled subaccount strictly and matches its code. A main request must not use automatic subaccount fallback. Return source mismatch without payment; do not reveal foreign-account details. New responses add payment_account_number identifying the actual authorized debit source; never confuse the returned main wallet account with the selected subaccount.

Under the main-account payment lock, calculate one immutable fee/split/period/policy snapshot, compare it with expected and use that **same snapshot** for the debit, distributions, metadata, participation context and response. Do not re-read a different amount after comparison. Reject changed consent with membership_fee_terms_changed409 before monetary effects. Year must remain the confirmed period at execution; recheck the anniversary boundary before effects and never label a new period with an old key. A policy change with unchanged user-visible values but a changed version still requires fresh consent.

Idempotency lookup remains before execution: a completed unchanged intent replays its original response even if current policy has changed. A mismatching body/key remains409 idempotency_key_reused. New confirmation/source changes need a new key only after a previous ambiguous intent is resolved. Existing status/key guarantees remain; no idempotency-table migration is proposed.

## Completion and accounting invariants found during inspection

These are source findings, not newly reproduced tests:

- distribute skips zero-valued parts, while legacy MembershipFeeStatusService recognizes fixed split-name sets. A valid configured two-part split may therefore not be recognized as paid. New distributions add server-generated expected_breakdown_gol and membership_fee_total_gol to membership transaction metadata. Paid status recognizes a complete, consistent positive split set of completed transactions whose actual amounts match that immutable metadata and whose period/user match. It must not use today's changed fee policy to reinterpret an old payment. Preserve existing canonical/legacy/operations-only evidence. Require successful paid proof inside the payment transaction before commit; incomplete/conflicting evidence rolls back new effects. Participation is awarded once only after completion is established. Do not accept a single marker without matching actual transfers.
- TransactionService recalculates a source main Account.balance as active+faded after transfers, while AccountBalanceService defines local total as active+availableDim+committedDim. Add a focused regression with committed Dim and correct only the relevant canonical total calculation if it reproduces. Preserve all ownership buckets and mirror rules. This shared-service change requires existing transaction/economy checks at the final server gate, not just membership tests.

No new receipt table or migration is introduced. If a focused test establishes that the metadata-backed proof cannot safely coexist with existing domain idempotency/legacy partial records, stop that implementation step and revise the specification rather than charging around the inconsistency.

## Native confirmation and state

Retain the existing wallet page. Unpaid members see Dim and Active choices and exact source availability. Selecting Active allows main or an eligible owned subaccount; switching the bucket never automatically submits. Freshly load the membership projection before constructing consent. Show fee, distribution, server period, bucket and exact debit account in the confirmation. Dim confirmation explains activation of the membership amount from available Dim. No client hard-coded12-Bahar charge or main-only Active restriction.

Create an immutable in-memory intent containing captured financial-session identity, selected source, expected snapshot and a cryptographically random idempotency key. One in-flight submit per intent; repeat taps share it. Freeze terms/source while submitting or outcome is ambiguous. Cancellation before sending creates no payment. Once submitted, a network cancellation or closing a dialog is not a proven rollback.

States: loading → ready → confirming → submitting → confirmedPaid / definiteRejected / outcomeUnknown. Terms/source/funds changed → refresh then new confirmation. request_in_progress and transport/timeout/5xx/malformed-success responses → outcomeUnknown; do not show a failure implying no debit. already_paid → reconcile authoritative GET. A valid success must match intent period, fee, split, bucket and actual debit account and has_paid=true before presenting a payment receipt; otherwise reconcile as unknown. Afterwards refresh wallet/history/membership independently; a refresh error cannot turn a known successful payment into failed payment.

## Retry, restart and authorization

For this financial request disable ApiClient automatic mutation retries via an additive per-request RequestContext flag with default behavior unchanged for all existing callers. This avoids an old captured authorization being silently reused after backoff. Explicit unchanged-intent retry first rechecks live session/bootstrap, sends the same immutable body/key and performs no source substitution. A retryable failure alone never creates a new key.

OutcomeUnknown offers authoritative membership GET. Same confirmed period paid resolves the membership obligation and is labelled paid status, not invented proof that this specific request executed. Different period or incomplete response leaves uncertainty. A negative GET alone does not prove an in-progress POST cannot subsequently complete; retry stays the same intent/key and a new intent is blocked while ambiguous.

401, logout or captured user/token/device changes close send authority, clear all financial presentation and reject delayed updates using existing controller guards. Temporary bootstrap blockage is recoverable and cannot send a POST. Dispose prevents publication from late responses. Do not persist the financial intent or raw key to an offline replay queue. After app restart/reopening, GET current status first; no automatic POST. Existing server period/domain idempotency remains the safety boundary after in-memory state is lost. Limit same-intent retry to23 hours from first submission, strictly below the middleware's one-day key lifetime, using an elapsed monotonic clock; expired intent cannot be silently retried and requires authoritative reconciliation/new confirmation.

## Required verification

Focused server regressions first: each Dim/main Active/owned subaccount Active happy path; Active keeps Dim unchanged; Dim excludes committed funds; reservations; foreign/disabled source; aggregate sufficient but no single eligible source; no fallback for new explicit main source; preserved old automatic fallback; partial/malformed expected fields; changed fee/year/split/version/source with zero effects; idempotent replay/body conflict; paid race; zero-valued parts and policy changes after payment; rollback on incomplete proof; committed-Dim stored totals; participation once; system treasury operation still works before trading unlock.

Focused native regressions first: server capability absent stays read-only; both buckets/source selection; exact confirmation; no send without confirmation; shared repeated taps; no automatic POST retry; same-key explicit retry; ambiguous-result GET and freeze; paid status versus own receipt; period mismatch; refresh after known success; session/bootstrap/401/disposal/late response boundaries; no financial offline queue.

One final relevant server verification and complete mobile suite after changes stabilize; avoid repeated broad CI on documentation or formatter commits. Then one independent whole-change code review and a single bounded fix-pass for valid findings. Package one new signed UAT candidate only after verified source. Real money/site/phone/provider/distribution acceptance remains pending and no live charge is authorized by this specification.

## Execution boundary

Server API changes must be deployed and their capability observed before new APK payment can be enabled for users; successful fixture CI does not prove host deployment. Server publishing/main merge is a separate action. The next stage is user review of this written specification, then an implementation plan. No product code or+18 APK is created in this design stage.
