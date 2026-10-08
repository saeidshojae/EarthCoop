# EarthCoop Temporal System & Chronicle — Design Spec v1.1

> **Implementation status — 2026-10-07:** The canonical Temporal System foundation and Communication Center integration are merged into `main`. The Chronicle bounded context was also already partially implemented before this reconciliation: `/chronicle`, the constitutional epoch, EarthCoop-year calculation, FA/EN/AR copy and Chronicle-specific tests exist outside Temporal core. The Chronicle completion adds canonical milestone persistence, admin management, public milestone rendering, navigation and sitemap discovery. Temporal and Chronicle remain separate bounded contexts with one-way Chronicle → Temporal dependency.

## Goal
Establish one canonical time architecture for EarthCoop and a separate Chronicle product for EarthCoop history.

## Temporal System invariants
- Canonical application/storage timezone remains UTC.
- `fa` defaults to Jalali; `en`, `ar`, and future unknown locales default to Gregorian unless policy overrides them.
- Localized strings are presentation/input concerns only and are never the business source of truth.
- `birth_date` is a LocalDate, not an Instant.
- Instant, LocalDate, LocalDateTime, Duration, and calendar periods are distinct concepts.
- Business logic never compares localized display strings.
- New temporal code prefers immutable values / CarbonImmutable.
- Calendar libraries are implementation details hidden behind EarthCoop interfaces.
- Core date input must not depend on a runtime external CDN.

## Temporal context
Human-facing parse/render operations resolve a TemporalContext containing locale, calendar, timezone, numbering system and style. Resolution order is explicit operation context, user preference, locale policy, then application fallback. Recipient-facing communications resolve context from the recipient, not from the running process locale.

## Calendar policy
Initial default policy:
- fa -> jalali
- en -> gregorian
- ar -> gregorian
- fallback -> gregorian

User override may be added later without changing domain contracts.

## Canonical types
### Instant
Used for `created_at`, `starts_at`, `ends_at`, `sent_at`, `scheduled_at`, audit timestamps and other precise moments. Canonical comparison uses UTC.

### LocalDate
Used where the civil day itself is the fact, especially `birth_date`. It must not be converted into a fake midnight UTC Instant.

### LocalDateTime
Used when a person chooses a local clock time plus timezone and the system then resolves it to an Instant.

## Temporal subsystem
Create a bounded context under `app/Temporal` with:
- TemporalService public contract
- CalendarAdapter contract
- TemporalContext and resolver
- LocalDate value object
- GregorianCalendarAdapter
- JalaliCalendarAdapter
- TemporalManager implementation
- presentation components for date, datetime and relative time
- localized input parsing and digit normalization

Morilog/Verta must not be called directly by controllers/views after a surface is migrated.

## Input and API
Human forms may accept localized input such as `۱۴۰۵/۰۷/۰۹`, but canonical API contracts use `YYYY-MM-DD` for civil dates and RFC3339/ISO8601 for Instants. Hidden canonical values are never trusted without server-side validation.

## Registration
Persian registration accepts Jalali birth dates; non-Persian registration accepts Gregorian dates. Both resolve to the same LocalDate canonical value. Age policy is separate from calendar conversion.

## Elections
Election lifecycle times remain canonical Instants. Localized rendering, countdowns and recipient notifications use Temporal System. Election business semantics do not change.

## Najm Bahar / Stock
Financial, salary, auction, ledger and audit times remain canonical. Human reports localize dates; machine exports remain ISO/RFC3339. Calendar conversion must not alter accounting semantics.

## Reporting
Rolling periods and calendar periods are distinct. A Jalali calendar-month report must query the actual Jalali month boundaries, not merely relabel Gregorian buckets.

## Communications
Emails, notifications and queued messages render dates from recipient TemporalContext. Running process locale must not leak into recipient output.

## Timezone
Timezone identifiers use IANA names. Browser-detected timezone may improve ordinary display but is not an authoritative legal/deadline source. Invalid/missing timezone follows deterministic fallback policy; sensitive paths must not silently invent semantics.

## Presentation
Provide EarthCoop Blade components that emit semantic `<time datetime="...">` markup. Relative time is locale-aware but calendar-independent. RTL output must keep machine timestamps direction-safe.

## Migration
No Big Bang. Migrate in vertical slices:

Current implementation note (2026-10-08): the central Temporal bounded context, canonical UTC handling, Jalali/Gregorian adapters, localized Blade components, localized date/date-time input paths, registration, elections, major group/social surfaces, Najm Bahar, Stock/Auction, admin/report surfaces and Communication Center temporal integration are implemented. Persian editable date/date-time inputs are **picker-first** through the single bundled Temporal Jalali picker; manual localized typing remains only a resilient fallback. The same shared component/runtime is required for user-facing and admin-facing forms, including birth dates and date filters. PR #223 closed the remaining date-time presentation/input debt; PR #225 closed the date-only presentation/filter/persistence layer. The repository-wide architecture guard has zero known direct legacy calendar-library debt outside the Jalali adapter and now also ratchets raw human-facing Blade date/date-time inputs and direct date rendering patterns.
1. Temporal core
2. Registration
3. Elections
4. Groups/social surfaces
5. Najm Bahar
6. Stock/Auction
7. Communication Center
8. Admin/reports/exports
9. remaining UI
10. Chronicle

No destructive data migration is authorized by this design. Any evidence of incorrectly stored historical data requires a separate data-audit checkpoint.

## Architecture guard
After migration, first-party application/view code must not directly use `Jalalian::`, `verta(`, hard-coded `toLocaleDateString('fa-IR')`, or localized parsing in controllers except through explicit temporary allowlists.

## Chronicle bounded context
Chronicle is separate from Temporal System and depends on it one-way.

**Current state (2026-10-07): implemented product surface.** The public `/chronicle` page, constitutional epoch and EarthCoop-year calculator already existed. The completion layer adds `chronicle_milestones` with canonical `occurred_on` dates, multilingual title/description content, publish/order controls, soft deletion and admin management. Published milestones are rendered on `/chronicle`, linked from public navigation and included in the sitemap. No Chronicle runtime logic is placed in `app/Temporal`.

### Epoch
- 1401/01/01 Solar Hijri
- 2022-03-21 Gregorian
- EarthCoop Year 1

The epoch is a version-controlled constitutional constant, not an everyday admin setting.

### EarthCoop year
For post-epoch dates, EarthCoop Year = Jalali Year - 1400. There is no Year 0. Dates before the epoch have no EarthCoop year.

### Product behavior
EarthCoop Year is not shown throughout operational UI. It is primarily displayed in `/chronicle`, which presents today, the EarthCoop year, the epoch and curated milestones. Milestones store canonical dates; EarthCoop year is derived and never stored.

## Testing constitution
Cover:
- locale/calendar resolution
- Jalali/Gregorian parsing and formatting
- Persian/Arabic-Indic digit normalization
- leap boundaries
- timezone midnight and DST boundaries
- recipient-aware communication rendering
- birth-date age boundary
- API machine contracts
- legacy input compatibility during migration
- Chronicle epoch/year boundaries

Targeted tests are the normal development gate. Full Validation is reserved for milestone merge candidates/final rollout.

## Definition of done
Temporal migration is complete when all user-facing date presentation/input passes through Temporal System, direct calendar-library usage is removed outside approved boundaries, localized filters/query periods are correct, communications/exports are context-aware, the architecture guard is green, and final Full Validation passes.

**Status against this DoD (2026-10-07): complete.** The architectural core, direct legacy-calendar removal, Communication Center integration, repository-wide date-time cleanup (PR #223), date-only cleanup (PR #225), architecture ratchets and final validation gates are green. The Temporal presentation/input migration is therefore considered closed.

Chronicle is complete when `/chronicle` correctly shows today and EarthCoop Year, epoch semantics are locked, milestones use canonical dates, admin management exists, localization is ready, and Chronicle has no effect on operational business date logic.

**Status against this Chronicle DoD (2026-10-07):** implemented. EarthCoop Year remains derived and is not persisted in the milestone table; only published milestones are exposed publicly; admin milestone dates enter through the shared Temporal date input/parser path; Chronicle is discoverable in navigation and sitemap; and operational business modules do not depend on Chronicle.
