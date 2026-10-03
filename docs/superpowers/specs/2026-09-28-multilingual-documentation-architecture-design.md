# طراحی معماری چندزبانه مرکز اسناد EarthCoop و مهاجرت تدریجی از Mintlify

**تاریخ:** 2026-09-28  
**Baseline:** `main@38ef89def3077f4280c7dd704f4c2b055ac95931`  
**Branch:** `agent/docs-multilingual-architecture-spec-20260928`  
**Repos in scope:** `saeidshojae/EarthCoop`, `saeidshojae/EarthCoop-docs`

## 1. هدف

ایجاد یک معماری واحد، چندزبانه و قابل‌ممیزی برای کل نظام اسناد EarthCoop که:

- `EarthCoop-docs` تنها منبع حقیقت محتوایی و رجیستری اسناد باشد؛
- فارسی، انگلیسی و عربی را به‌صورت نسخه‌های زبانی یک سند واحد مدیریت کند؛
- اسناد حقوقی/مرجع را از راهنماهای محصول و مستندات فنی جدا نگه دارد؛
- وضعیت واقعی قابلیت‌های محصول را از قابلیت‌های در حال توسعه و برنامه‌ریزی‌شده تفکیک کند؛
- ترجمه را از «استنتاج آزاد هوش مصنوعی» به «ترجمه کنترل‌شده از منبع مرجع» تبدیل کند؛
- مرکز اسناد اختصاصی EarthCoop را به مقصد اصلی نمایش تبدیل کند؛
- Mintlify را تا پایان مهاجرت فقط به‌عنوان Renderer/Host موقت یا پشتیبان نگه دارد؛
- مشکل نام و محتوای قدیمی `NewEarthCoop` و سایر راهنماهای ناسازگار با واقعیت پروژه را به‌صورت نظام‌مند حذف کند.

## 2. وضعیت فعلی و مسئله

### 2.1 دو منبع نمایشی، یک حقیقت نامشخص

در حال حاضر `EarthCoop-docs` مخزن اصلی اسناد است و `docs-manifest.json` نقش رجیستری مرکز دانش را دارد، اما نمایش عمومی `docs.earthcoop.ir` هنوز توسط Mintlify انجام می‌شود. مرکز اسناد جدید EarthCoop نیز فعلاً تک‌زبانه است و برای سه‌زبانه شدن قرارداد محتوایی استاندارد ندارد.

### 2.2 محتوای انگلیسی موجود قابل اعتماد نیست

بخش قابل توجهی از صفحات انگلیسی موجود با وضعیت فعلی EarthCoop همخوان نیست. نمونه‌های مشاهده‌شده شامل توصیف‌های قدیمی یا نادرست درباره ساخت گروه، مدل پروژه، Najm Bahar، Gol، انتقال سرمایه، Najm Hoda و حتی نام قدیمی `NewEarthCoop` هستند.

قاعده قطعی این طراحی:

> هیچ متن راهنمای محصول نباید صرفاً بر اساس حدس، استنتاج یا تولید آزاد AI به‌عنوان واقعیت فعلی EarthCoop منتشر شود.

### 2.3 مشکل locale فارسی در Mintlify

Mintlify در پیکربندی فعلی locale فارسی واقعی ارائه نمی‌کند و از `ar` به‌عنوان workaround فنی RTL استفاده شده است. این workaround نباید به معماری نهایی تبدیل شود. تا زمان خروج از Mintlify، فقط به‌عنوان سازوکار انتقالی نگه داشته می‌شود.

## 3. اصول معماری

1. **One Document Identity:** هر سند یک شناسه پایدار دارد؛ زبان‌ها renditionهای همان سند هستند، نه سندهای مستقل.
2. **Persian Canonical by Default:** برای اسناد حقوقی و مرجع، فارسی زبان مرجع است مگر سند صریحاً خلاف آن را تعیین کند.
3. **Translation Is Derived Content:** نسخه انگلیسی و عربی باید از متن مرجع ترجمه شوند، نه از برداشت آزاد درباره پروژه.
4. **Product Truth Is Evidence-Based:** راهنمای محصول فقط چیزی را «موجود» معرفی می‌کند که در Production یا قرارداد رسمی وضعیت فعلی، شواهد معتبر دارد.
5. **Status Is Explicit:** هر قابلیت راهنما یکی از `available`, `in_development`, `planned` را دارد.
6. **Renderer Is Replaceable:** Mintlify یا هر Frontend دیگر نباید Source of Truth باشد.
7. **No Silent Drift:** تغییر متن مرجع باید ترجمه‌های وابسته را به وضعیت نیازمند بازبینی ببرد.
8. **Legal Status Is Separate From Publication:** انتشار، ترجمه، ایندکس یا نمایش، اثر حقوقی جدید ایجاد نمی‌کند.

## 4. مدل محتوایی

### 4.1 هویت سند

هر سند/راهنما باید حداقل این ویژگی‌ها را داشته باشد:

```json
{
  "document_id": "ECON-REF-01",
  "content_class": "reference",
  "canonical_language": "fa",
  "version": "0.1",
  "legal_status": "official_draft",
  "product_status": null
}
```

### 4.2 renditionهای زبانی

```json
{
  "renditions": {
    "fa": {
      "path": "fa/reference/economy/econ-ref-01.md",
      "status": "current",
      "source_version": "0.1"
    },
    "en": {
      "path": "en/reference/economy/econ-ref-01.md",
      "status": "not_translated",
      "source_version": null
    },
    "ar": {
      "path": "ar/reference/economy/econ-ref-01.md",
      "status": "not_translated",
      "source_version": null
    }
  }
}
```

وضعیت ترجمه فقط یکی از این‌هاست:

- `current`
- `needs_review`
- `outdated`
- `not_translated`

### 4.3 وضعیت راهنمای محصول

برای راهنماهای محصول، هر قابلیت یا صفحه باید وضعیت کلی قابل نمایش داشته باشد:

- `available` — در محصول فعلی واقعاً قابل استفاده است؛
- `in_development` — مصوب و در حال پیاده‌سازی است؛
- `planned` — در معماری/برنامه وجود دارد ولی هنوز Feature فعلی نیست.

هیچ Feature نباید بدون شواهد معتبر `available` شود.

## 5. طبقه‌بندی محتوای مرکز اسناد

چهار خانواده اصلی:

### A. اسناد بنیادین و قوانین

نمونه: PRIN، CHARTER، CONST، ECON، JUD.  
ویژگی‌ها: نسخه‌بندی حقوقی، status حقوقی، فارسی مرجع، ترجمه غیرنافذ مگر خلاف آن تصریح شود.

### B. اسناد مرجع و استانداردها

نمونه: ECON-REF-01، STD، Policyها و Concordanceها.  
ویژگی‌ها: ردیابی به سند حاکم، نسخه‌بندی، امکان status `official_draft` یا `effective`.

### C. راهنمای استفاده از EarthCoop

نمونه: ثبت‌نام، مکان و حکمرانی، گروه‌ها، انتخابات، پروژه‌ها، مشارکت، Najm Bahar، Najm Hoda.  
ویژگی‌ها: truth مبتنی بر محصول فعلی؛ status قابلیت‌ها الزامی؛ متن نباید وعده آینده را به شکل واقعیت جاری بیان کند.

### D. معماری و مستندات فنی/API

ویژگی‌ها: مناسب توسعه‌دهنده/همکار فنی؛ می‌تواند به نسخه کد، API یا commit متصل باشد؛ نباید با راهنمای عضو عمومی مخلوط شود.

## 6. منبع حقیقت برای راهنماهای محصول

برای هر ادعای محصول، ترتیب اعتبار به این صورت است:

1. وضعیت واقعی Production و UI قابل مشاهده؛
2. کد و تست‌های معتبر در `EarthCoop`؛
3. Spec/Plan تأییدشده و وضعیت اجرای آن؛
4. اسناد بنیادین و مرجع؛
5. محتوای قدیمی راهنما فقط به‌عنوان ورودی ممیزی، نه حقیقت.

اگر میان این منابع تعارض باشد، راهنما نباید حدس بزند. مورد باید در audit ثبت و تا تعیین تکلیف با زبان محتاطانه یا status پایین‌تر نمایش داده شود.

## 7. ممیزی محتوای انگلیسی موجود

تمام صفحات انگلیسی موجود باید صفحه‌به‌صفحه به سه دسته تقسیم شوند:

- **Keep/Correct** — ساختار مفید است ولی جزئیات باید با واقعیت اصلاح شود؛
- **Rewrite** — بخش عمده متن بر مدل قدیمی/استنتاج AI بنا شده؛
- **Remove/Archive** — صفحه به Feature یا نام منسوخ مربوط است و جایگاهی در راهنمای فعلی ندارد.

حداقل موارد اجباری ممیزی:

- تمام occurrenceهای `NewEarthCoop`؛
- ثبت‌نام و KYC؛
- ساختار سه‌گانه گروه‌های سیستمی و نقش Active/Observer؛
- Location/Governance جدید؛
- انتخابات سیستمی؛
- پروژه‌های عمومی و مسیر حمایت/بررسی؛
- اقتصاد و Najm Bahar مطابق ECON 0.2؛
- Bahar/Gol و ممنوعیت معرفی Gol به‌عنوان ارز مستقل؛
- VPU/سهم ارزش؛
- Najm Hoda و تفکیک قابلیت موجود از معماری آینده؛
- Marketplace و سایر بخش‌های هنوز توسعه‌نیافته؛
- لینک‌ها، نام محصول، دامنه‌ها و نام مخازن.

## 8. قرارداد ترجمه

### 8.1 جریان ترجمه

```text
Canonical source updated
  → dependent translations marked needs_review/outdated
  → AI draft translation
  → terminology validation
  → semantic comparison to canonical
  → review
  → status=current
```

### 8.2 ممنوعیت‌ها

- ترجمه حق افزودن Feature یا حکم تازه ندارد؛
- مترجم/AI حق خلاصه‌سازی ماهوی سند حقوقی را ندارد مگر نوع خروجی صریحاً summary باشد؛
- ترجمه نباید وضعیت `planned` را `available` کند؛
- نبود ترجمه بهتر از ترجمه حدسی یا قدیمی است.

## 9. واژه‌نامه سه‌زبانه

یک registry مستقل برای واژگان رسمی ایجاد می‌شود. هر term شامل:

```json
{
  "term_id": "activation",
  "fa": "فعال‌سازی",
  "en": "Activation",
  "ar": "التفعيل",
  "scope": "economy",
  "notes": "Do not translate as issuance or creation"
}
```

واژه‌نامه باید برای lint ترجمه‌ها قابل استفاده باشد و اصطلاحات مهم اقتصادی/حکمرانی در هر سه زبان یکسان بمانند.

## 10. URL و زبان در مرکز اسناد جدید

مرکز اسناد اختصاصی باید locale واقعی داشته باشد:

```text
/fa/...
/en/...
/ar/...
```

قواعد:

- language switcher فقط rendition موجود/current را مستقیماً باز کند؛
- اگر ترجمه موجود نیست، صفحه مرجع را نشان دهد و وضعیت «ترجمه در دسترس نیست» را واضح اعلام کند؛
- permalink سند از `document_id + locale + version/slug` مشتق شود؛
- canonical metadata برای موتور جست‌وجو مشخص باشد؛
- RTL برای فارسی/عربی واقعی و LTR برای انگلیسی اعمال شود.

## 11. نقش `docs-manifest.json`

`docs-manifest.json` از registry تک‌زبانه به registry چندزبانه ارتقا می‌یابد، بدون شکستن مصرف‌کنندگان فعلی در یک مرحله.

مهاجرت schema باید versioned باشد، مثلاً:

```json
{
  "schemaVersion": 2,
  "documents": []
}
```

در دوره گذار، parser باید schema قدیمی را هم بخواند یا migration یک‌باره کامل و تست‌شده انجام شود. انتخاب دقیق در implementation plan تعیین می‌شود، اما شکستن silent consumer مجاز نیست.

## 12. معماری Rendererها در دوره گذار

### 12.1 وضعیت انتقالی

```text
EarthCoop-docs
  → docs-manifest + language renditions
      → EarthCoop Docs Center (primary candidate)
      → Mintlify (temporary renderer/host)
```

### 12.2 Mintlify

تا زمان تکمیل مرکز جدید:

- انگلیسی آن باید با محتوای ممیزی‌شده تغذیه شود؛
- workaround `ar` برای فارسی فقط در صورت امکان عملیاتی حفظ می‌شود؛
- هیچ ساختار جدیدی نباید وابستگی بلندمدت به hack `ar=fa` ایجاد کند؛
- مشکل صفحه ریشه قدیمی `NewEarthCoop` باید ریشه‌یابی و حذف شود؛
- در پایان مهاجرت، Mintlify یا preview/mirror می‌شود یا حذف می‌شود.

## 13. مرکز اسناد اختصاصی EarthCoop

قابلیت‌های اجباری برای رسیدن به جایگزین production:

- سه locale واقعی `fa/en/ar`؛
- جست‌وجوی full-text در هر زبان؛
- فهرست مطالب؛
- نمایش status حقوقی و وضعیت ترجمه؛
- version selector برای اسناد نسخه‌دار؛
- language switcher مبتنی بر rendition؛
- copy/print سالم؛
- deep link به heading/clause؛
- عدم ترکیب اسناد نافذ و draft بدون برچسب؛
- قابلیت ingest از `EarthCoop-docs` و `docs-manifest` بدون کپی دستی محتوا.

قابلیت‌های بازخورد بندبه‌بند و queue نظرات می‌توانند در فاز بعد باشند و نباید مهاجرت چندزبانه را بلوکه کنند.

## 14. Data Flow

```text
Authoritative FA source / verified product facts
  ↓
EarthCoop-docs content + metadata
  ↓
docs-manifest registry
  ↓
Translation pipeline
  ↓
FA / EN / AR renditions
  ↓
Validation + terminology lint + stale detection
  ↓
Docs Center renderer
  └→ Mintlify transitional renderer
```

## 15. خطاها و Fail-Safeها

- اگر ترجمه قدیمی شد، status خودکار یا توسط CI به `needs_review/outdated` برود؛
- اگر rendition مسیر نامعتبر دارد، build باید fail شود؛
- اگر `document_id` تکراری است، validation fail شود؛
- اگر زبان مرجع وجود ندارد، سند publish نشود؛
- اگر صفحه راهنما `available` است ولی evidence contract ندارد، validation یا audit باید آن را flag کند؛
- اگر renderer قادر به نمایش locale نیست، نباید silently زبان دیگری را به‌عنوان آن locale معرفی کند.

## 16. تست و اعتبارسنجی

### 16.1 Registry tests

- uniqueness `document_id`؛
- valid locale set؛
- canonical rendition exists؛
- translation status enum؛
- version/source-version consistency؛
- no broken rendition paths.

### 16.2 Content tests

- ممنوعیت `NewEarthCoop` در محتوای عمومی جاری مگر در history/migration note؛
- terminology lint برای اصطلاحات حساس؛
- no `available` claim without evidence metadata در راهنماهای audited؛
- internal link validation per locale.

### 16.3 UI tests مرکز اسناد

- locale routing؛
- RTL/LTR؛
- language switching؛
- fallback when translation missing؛
- search isolation/coverage by locale؛
- version/status rendering.

### 16.4 Migration tests

- current Persian documents remain accessible؛
- English routes do not regress؛
- old inbound links redirect where needed؛
- no legal status changes as side effect of translation/migration.

## 17. مراحل مهاجرت

### Phase 0 — Inventory & Audit

- فهرست کامل صفحات فعلی Mintlify؛
- ریشه‌یابی صفحه root قدیمی `NewEarthCoop`؛
- ممیزی انگلیسی با evidence matrix؛
- تعیین صفحات rewrite/archive.

### Phase 1 — Multilingual Registry Contract

- schema v2 برای manifest؛
- rendition/status/canonical language؛
- واژه‌نامه سه‌زبانه؛
- validation.

### Phase 2 — English Truth Rewrite

- بازنویسی راهنماهای انگلیسی بر اساس وضعیت واقعی؛
- برچسب Available / In development / Planned؛
- حذف legacy naming و feature claims.

### Phase 3 — Arabic Translation

- ترجمه از canonical/current sources؛
- terminology validation؛
- status tracking.

### Phase 4 — Docs Center Multilingual UI

- `/fa`, `/en`, `/ar`؛
- switcher، search، status، fallback.

### Phase 5 — Mintlify Exit

- parity gate؛
- switch `docs.earthcoop.ir` به مرکز اختصاصی؛
- Mintlify به preview/mirror یا retirement.

## 18. معیار خروج از Mintlify

Mintlify فقط وقتی از مسیر production حذف می‌شود که:

1. همه اسناد فارسی فعلی در مرکز جدید قابل دسترسی باشند؛
2. EN و AR برای مجموعه تعیین‌شده launch coverage داشته باشند؛
3. جست‌وجو، لینک مستقیم، print/copy و navigation پایدار باشند؛
4. redirectهای ضروری آماده باشند؛
5. status حقوقی/نسخه/ترجمه درست نمایش داده شود؛
6. regression test مسیرهای عمومی سبز باشد؛
7. rollback plan برای DNS/hosting موجود باشد.

## 19. محدوده خارج از این طراحی

- ترجمه کامل فوری همه اسناد به عربی و انگلیسی؛
- خودکارسازی تصمیم حقوقی درباره status اسناد؛
- بازطراحی همه UIهای EarthCoop؛
- تغییر محتوای حقوقی ECON/CONST و اسناد بنیادین؛
- حذف فوری Mintlify پیش از parity.

## 20. تصمیم‌های قطعی طراحی

- `EarthCoop-docs` تنها Source of Truth محتوایی است؛
- فارسی canonical پیش‌فرض برای اسناد حقوقی/مرجع است؛
- EN/AR rendition هستند، نه سند مستقل؛
- راهنمای محصول evidence-based است؛
- status `available/in_development/planned` الزامی است؛
- AI فقط draft translator/editor است، نه منبع واقعیت پروژه؛
- مرکز اسناد اختصاصی مقصد نهایی production است؛
- Mintlify فقط transitional renderer/host است؛
- workaround عربی برای فارسی معماری نهایی نیست؛
- مهاجرت باید تدریجی، قابل rollback و بدون تغییر ناخواسته وضعیت حقوقی اسناد باشد.

## 21. معیار پذیرش طراحی

این طراحی موفق است اگر پس از اجرا:

- کاربر در `docs.earthcoop.ir` سه زبان واقعی ببیند؛
- هیچ صفحه جاری به نام `NewEarthCoop` یا مدل منسوخ پروژه/اقتصاد تکیه نکند؛
- هر ترجمه بتواند به نسخه مرجع خود ردیابی شود؛
- تغییر متن مرجع، stale بودن ترجمه‌ها را آشکار کند؛
- قابلیت‌های آینده به‌عنوان قابلیت موجود معرفی نشوند؛
- Renderer قابل تعویض باشد و حذف Mintlify نیازمند مهاجرت مجدد محتوا نباشد.
