# EarthCoop Persian Topic / Entity Map

Date: 2026-10-05

Status: Phase-1 source-of-truth entity inventory. This document defines what EarthCoop may accurately say before keyword/SERP demand is mapped.

## Schema and safety rules

Each entity uses:

`id | official_fa_name | english_working_label | definition | stability | source_evidence | relation_to_earthcoop | allowed_public_claims | prohibited_or_unverified_equivalences`

Allowed stability values are exactly:

- `stable`
- `stable-core-evolving-details`
- `draft`
- `experimental`

External/neighbor concepts use relationship labels:

- `exact` — the term accurately names the EarthCoop concept/mechanic.
- `adjacent` — meaningful conceptual overlap, but not identity.
- `comparison` — useful primarily for explaining differences/similarities.
- `do-not-target` — inaccurate, misleading, or insufficiently supported as an EarthCoop target.

A concept may be stable at its core while parameters/details remain evolving. SEO content may index the stable core and must not promote draft details as fixed public facts.

---

## A. Identity and cooperation

### `EC-BRAND` — ارث‌کوپ / EarthCoop

- **official_fa_name:** ارث‌کوپ (EarthCoop)
- **english_working_label:** EarthCoop
- **definition:** A global cooperative/participatory system whose name expresses cooperation over Earth/shared inheritance; designed to organize shared rights, participation, governance and an economic framework around Earth and shared resources.
- **stability:** `stable`
- **source_evidence:** `درباره-نامگذاری-ها`; approved Persian SEO design; FC/CH/CO foundational principles.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** EarthCoop may describe itself as a cooperative/participatory system built around common rights in Earth/resources, participation and accountable governance.
- **prohibited_or_unverified_equivalences:** Do not present EarthCoop as a state, replacement government, or already-recognized international institution.

### `EC-GLOBAL-COOP` — تعاون/تعاونی جهانی

- **official_fa_name:** تعاون جهانی / تعاونی جهانی (public search-language cluster; final primary wording is SERP-dependent)
- **english_working_label:** global cooperation / global cooperative
- **definition:** Cooperation organized across local, national, continental and global levels; EarthCoop connects local communities upward through shared systems and representation.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** LOC-005/006 and EarthCoop foundational scope.
- **relation_to_earthcoop:** `exact` for global cooperative architecture in the broad descriptive sense; exact legal category in every jurisdiction is not asserted.
- **allowed_public_claims:** EarthCoop is designed for local-to-global cooperative organization.
- **prohibited_or_unverified_equivalences:** Do not claim a single existing statutory cooperative form covers EarthCoop globally.

### `EC-DIGITAL-COOP` — تعاونی دیجیتال

- **official_fa_name:** تعاونی دیجیتال (working public label)
- **english_working_label:** digital cooperative
- **definition:** Cooperative/participatory organization implemented through digital infrastructure for membership, groups, governance, financial systems and projects.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** EX operational architecture; DG digital-governance scope.
- **relation_to_earthcoop:** `adjacent`
- **allowed_public_claims:** EarthCoop has a strongly digital cooperative architecture.
- **prohibited_or_unverified_equivalences:** Do not reduce EarthCoop to only a software marketplace or standard platform cooperative.

### `EXT-PLATFORM-COOP` — Platform Cooperative / تعاونی پلتفرمی

- **official_fa_name:** تعاونی پلتفرمی
- **english_working_label:** platform cooperative
- **definition:** External established family of cooperatively owned/governed digital platforms, useful for comparison with EarthCoop.
- **stability:** `stable`
- **source_evidence:** external literature to be captured in SERP/research phase; EarthCoop internal architecture for comparison.
- **relation_to_earthcoop:** `comparison`
- **allowed_public_claims:** EarthCoop may be compared with platform cooperativism where governance/ownership mechanics overlap.
- **prohibited_or_unverified_equivalences:** Do not assert EarthCoop is exactly a conventional platform cooperative without a criteria-by-criteria comparison.

---

## B. Justice, rights, freedom, equality and dignity

### `EC-JUSTICE` — عدالت

- **official_fa_name:** عدالت
- **english_working_label:** justice
- **definition:** EarthCoop's foundational framing is to put each thing in its proper place and give each right to its rightful holder; this framing places Earth/shared resources within a common-right structure and connects rights with responsibility.
- **stability:** `stable`
- **source_evidence:** project purpose statement; CO-005; CH foundational principles.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Justice is a foundational design objective of EarthCoop and the economy is an instrument for realizing it.
- **prohibited_or_unverified_equivalences:** Do not claim EarthCoop's definition is the universally accepted definition of justice.

### `EC-FUNDAMENTAL-RIGHTS` — حقوق بنیادین و حق همگانی

- **official_fa_name:** حقوق بنیادین / حق همگانی
- **english_working_label:** fundamental and common rights
- **definition:** Rights grounded in human dignity and the common interest/right in Earth and foundational resources; in EarthCoop this grounds participation, oversight, benefit and responsibility.
- **stability:** `stable`
- **source_evidence:** CO-006/007; CH-008/009.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** EarthCoop explicitly recognizes foundational rights, participation, oversight, responsible freedom and shared benefit/responsibility.
- **prohibited_or_unverified_equivalences:** Do not automatically equate every EarthCoop foundational right with a specific external legal doctrine of natural law.

### `EXT-NATURAL-RIGHTS` — حقوق طبیعی / حقوق ذاتی

- **official_fa_name:** حقوق طبیعی / حقوق ذاتی
- **english_working_label:** natural / inherent rights
- **definition:** External philosophical/legal vocabulary potentially adjacent to EarthCoop's claims about rights arising from being human and common rights in Earth/resources.
- **stability:** `stable`
- **source_evidence:** external literature to be researched; CO/CH wording uses foundational/inherent dignity and rights.
- **relation_to_earthcoop:** `comparison`
- **allowed_public_claims:** EarthCoop can compare its foundational-rights framework with natural/inherent-rights traditions.
- **prohibited_or_unverified_equivalences:** Do not say EarthCoop formally adopts a particular natural-law school unless its official documents do so.

### `EC-DIGNITY` — کرامت انسانی

- **official_fa_name:** کرامت ذاتی انسان
- **english_working_label:** human dignity
- **definition:** Human beings are not mere instruments of production, consumption, voting, data, profit or control; systems and algorithms remain subordinate to human dignity.
- **stability:** `stable`
- **source_evidence:** CH-009; CO-007; DG principles.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Human dignity is a constitutional/design constraint on economic, governance and technical systems.
- **prohibited_or_unverified_equivalences:** None beyond avoiding claims of external legal recognition not established by evidence.

### `EC-RESPONSIBLE-FREEDOM` — آزادی مسئولانه / آزادی عادلانه

- **official_fa_name:** آزادی مسئولانه; «آزادی عادلانه» is an approved explanatory phrase, not the formal constitutional term.
- **english_working_label:** responsible freedom / fair freedom
- **definition:** Freedom protected alongside the rights of others, common rights, accountability and non-domination.
- **stability:** `stable`
- **source_evidence:** CH-009; CO-007; ECON-004; approved SEO design clarification.
- **relation_to_earthcoop:** `exact` for آزادی مسئولانه; `adjacent` explanatory wording for آزادی عادلانه.
- **allowed_public_claims:** EarthCoop treats freedom as a fundamental value bounded by legitimate responsibility and others' rights.
- **prohibited_or_unverified_equivalences:** Do not equate responsible freedom with a specific partisan/political ideology.

### `EC-FUNDAMENTAL-EQUALITY` — برابری بنیادین / برابری عادلانه

- **official_fa_name:** برابری بنیادین; «برابری عادلانه» is explanatory wording.
- **english_working_label:** foundational equality / fair equality
- **definition:** Human beings are equal in foundational rights relating to Earth/shared resources regardless of citizenship, wealth, language, gender, belief, status or other listed differences; this is intended to become real participation, not merely formal wording.
- **stability:** `stable`
- **source_evidence:** CH-010; CO-008.
- **relation_to_earthcoop:** `exact` for برابری بنیادین; `adjacent` explanatory wording for برابری عادلانه.
- **allowed_public_claims:** EarthCoop recognizes foundational equality of rights and rejects discrimination in those rights.
- **prohibited_or_unverified_equivalences:** Do not claim equality of outcomes or identical rewards unless a specific rule says so.

---

## C. Land, commons and ownership

### `EC-COMMON-RIGHT-EARTH` — حق همگانی نسبت به زمین و منابع

- **official_fa_name:** حق همگانی نسبت به زمین و منابع بنیادین حیات
- **english_working_label:** common right in Earth and foundational resources
- **definition:** Earth, foundational natural resources and shared life-supporting capacities are subjects of common right rather than unlimited exclusionary ownership.
- **stability:** `stable`
- **source_evidence:** CO-005/006; ECON-006; foundational project purpose.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** This common-right principle is foundational to EarthCoop governance/economic design.
- **prohibited_or_unverified_equivalences:** Do not imply that current external property law everywhere already recognizes EarthCoop's formulation.

### `EC-PRIVATE-LABOR-OWNERSHIP` — مالکیت خصوصی مشروع بر دسترنج

- **official_fa_name:** مالکیت خصوصی مشروع بر دسترنج
- **english_working_label:** legitimate private ownership of the fruits of labor
- **definition:** EarthCoop respects legitimate private ownership of work, production, creativity, service, knowledge, work tools, lawful assets and responsible investment, subject to non-monopoly and common-right constraints.
- **stability:** `stable`
- **source_evidence:** CO-005; ECON-005.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Common rights in land/resources do not negate legitimate private ownership of one's work and lawfully created/acquired property.
- **prohibited_or_unverified_equivalences:** Do not describe EarthCoop as abolishing private property.

### `EC-COMMON-OWNERSHIP-RIGHT` — حق مالکانه همگانی

- **official_fa_name:** حق مالکانه همگانی
- **english_working_label:** common ownership entitlement
- **definition:** The economic, legal and ethical share/right of all entitled parties arising from exclusive/economic exploitation of land and common resources.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** ECON-007 through ECON-012; CO-046.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** The core principle and purpose may be publicly explained, including compensation for exclusive use, public funds/projects, ecological restoration, future generations and reduction of resource-derived concentration.
- **prohibited_or_unverified_equivalences:** Do not publish final rates/calculation formulas/distribution parameters until formally stabilized.

### `EXT-COMMONS` — Commons / منابع مشترک

- **official_fa_name:** منابع مشترک / مشترکات
- **english_working_label:** commons
- **definition:** External conceptual family concerning shared resources and governance, overlapping with EarthCoop's common-right model.
- **stability:** `stable`
- **source_evidence:** external commons literature to be researched; EarthCoop CO/ECON common-resource provisions.
- **relation_to_earthcoop:** `adjacent`
- **allowed_public_claims:** Use for explanation/comparison of shared-resource governance.
- **prohibited_or_unverified_equivalences:** Do not claim EarthCoop's common-right/legal-economic mechanism is identical to every commons framework.

---

## D. Governance, participation and elections

### `EC-DEMOCRATIC-GOVERNANCE` — حکمرانی دموکراتیک و مشارکتی

- **official_fa_name:** حکمرانی دموکراتیک / حکمرانی مشارکتی
- **english_working_label:** democratic / participatory governance
- **definition:** Governance based on member participation, oversight, accountability, elected roles and multilevel organization, with safeguards against concentration of power.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** CO-005; CH governance principles; EX governance/election sections.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** EarthCoop is designed around participatory/democratic member governance and oversight.
- **prohibited_or_unverified_equivalences:** Do not imply all governance mechanics are already operational or legally recognized everywhere.

### `EC-CONTINUOUS-ELECTIONS` — انتخابات دائمی / مستمر بدون نامزد

- **official_fa_name:** انتخابات دائمی بدون نامزد
- **english_working_label:** continuous candidate-less elections
- **definition:** EarthCoop's primary elections are documented as systemic, automatic, permanent/continuous and candidate-less, with multilevel operation.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** EX-019 and following election provisions; EX-079/080.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Explain the permanent/continuous, system-mediated, candidate-less core mechanic and its purpose.
- **prohibited_or_unverified_equivalences:** Do not treat every search phrase such as «انتخابات پویا» as formal EarthCoop terminology until SERP/intention mapping; do not state parameter values as permanent if configurable.

### `EXT-LIQUID-DEMOCRACY` — دموکراسی سیال

- **official_fa_name:** دموکراسی سیال
- **english_working_label:** liquid democracy
- **definition:** External governance model combining direct participation and delegable/transferable representation; useful as a comparison target.
- **stability:** `stable`
- **source_evidence:** external literature to be captured in Task 3; EarthCoop election mechanics for comparison.
- **relation_to_earthcoop:** `comparison`
- **allowed_public_claims:** Compare similarities/differences where evidence supports them.
- **prohibited_or_unverified_equivalences:** Do not call EarthCoop's election system liquid democracy as a synonym without mechanic-level proof.

### `EC-LOCAL-TO-GLOBAL` — خودگردانی محلی و پیوند محلی تا جهانی

- **official_fa_name:** خودگردانی محلی / پیوند محلی تا جهانی
- **english_working_label:** local autonomy and local-to-global governance
- **definition:** Local communities manage legitimate nearby affairs and connect upward through ascending representation, digital systems, assemblies, professions/projects and shared standards, while central/global levels must not erase legitimate local authority without justification.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** LOC-005/006/007.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** EarthCoop has a locality-first, multilevel architecture extending from neighborhood/local community toward global levels.
- **prohibited_or_unverified_equivalences:** Do not automatically label the architecture federalism/confederalism without formal criteria analysis.

### `EC-PARTICIPATION-OVERSIGHT` — حق مشارکت و نظارت همگانی

- **official_fa_name:** حق مشارکت و نظارت همگانی
- **english_working_label:** common participation and oversight rights
- **definition:** Common rights require meaningful participation; participation requires oversight; oversight requires transparency and the ability to object/correct.
- **stability:** `stable`
- **source_evidence:** CH-008; CO-005/006.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Participation, oversight, transparency and objection/correction form a linked accountability chain.
- **prohibited_or_unverified_equivalences:** None beyond avoiding claims that every public user has identical direct powers at every level; role/level rules matter.

---

## E. Economic model

### `EC-PEOPLES-FREE-ECONOMY` — اقتصاد آزاد مردمی

- **official_fa_name:** اقتصاد آزاد مردمی
- **english_working_label:** People's Free Economy
- **definition:** EarthCoop's named economic model: freedom to work, create, produce, invest, trade and own legitimate fruits of labor, combined with broad people's participation, protection of common rights in land/resources, healthy competition, anti-monopoly safeguards, transparency/auditability, security/privacy and responsibility toward shared resources/future generations.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** approved Persian SEO design decision; underlying principles in ECON-002 through ECON-006, CO-045/046 and CH-017/018.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** This is the name of the economic model proposed/used by EarthCoop and EarthCoop is designed to realize it as a fair economic framework.
- **prohibited_or_unverified_equivalences:** Do not claim it is already an established academic school, universally recognized theory, laissez-faire capitalism, state socialism, or a conventional cooperative economy.

### `EC-PEOPLES-ECONOMY` — اقتصاد مردمی

- **official_fa_name:** اقتصاد مردمی
- **english_working_label:** people's economy / people-participatory economy
- **definition:** Broad public participation in ownership, production, investment, cooperation and economic decision-making; a public-language component of the wider EarthCoop economic model.
- **stability:** `stable`
- **source_evidence:** ECON-003 participation principle; approved model definition.
- **relation_to_earthcoop:** `adjacent`
- **allowed_public_claims:** People's economic participation is a core dimension of اقتصاد آزاد مردمی.
- **prohibited_or_unverified_equivalences:** Do not imply every external use of «اقتصاد مردمی» means EarthCoop's specific model.

### `EC-GLASS-ECONOMY` — اقتصاد شیشه‌ای

- **official_fa_name:** اقتصاد شیشه‌ای
- **english_working_label:** glass economy / transparent-and-secure economy
- **definition:** EarthCoop concept for an economy that is simultaneously transparent and secure: institutional rules/flows/public funds/projects/audit trails are observable to the legitimate extent («بانک شفاف و چراغ‌ها روشن»), while security, integrity, resilience, anti-fraud controls and legitimate privacy remain strong («شیشه ضدگلوله و نشکن»).
- **stability:** `stable-core-evolving-details`
- **source_evidence:** approved Persian SEO design definition; ECON-003, ECON-013/017; CO-047; DG security/privacy/audit principles.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** EarthCoop uses «اقتصاد شیشه‌ای» to describe transparency + security together.
- **prohibited_or_unverified_equivalences:** Do not define it as total public exposure of personal finances; legitimate privacy is part of the concept.

### `EXT-FREE-MARKET` — بازار آزاد

- **official_fa_name:** بازار آزاد
- **english_working_label:** free market
- **definition:** External economic concept useful for discussing freedom of exchange/enterprise and the EarthCoop anti-monopoly/common-right constraints.
- **stability:** `stable`
- **source_evidence:** external economic literature; EarthCoop ECON market/private ownership/anti-monopoly provisions.
- **relation_to_earthcoop:** `comparison`
- **allowed_public_claims:** EarthCoop can discuss open economic freedom and competition while explaining its common-right, transparency and anti-monopoly boundaries.
- **prohibited_or_unverified_equivalences:** Do not label اقتصاد آزاد مردمی as synonymous with laissez-faire/free-market capitalism.

---

## F. Money and financial infrastructure

### `EC-NAJM-BAHAR` — نجم‌بهار

- **official_fa_name:** نجم‌بهار
- **english_working_label:** Najm Bahar financial system
- **definition:** EarthCoop's internal financial system for accounts, balances, Bahar management/activation, transactions, funds, projects, market, investment, audit and financial reporting.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** ECON-013 through ECON-017; EX-041; naming document.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Describe its stable role and requirements: transparent, secure, auditable, privacy-aware.
- **prohibited_or_unverified_equivalences:** Do not call it a licensed bank or external banking institution unless legal/licensing facts establish that status.

### `EC-BAHAR` — بهار

- **official_fa_name:** بهار
- **english_working_label:** Bahar internal monetary unit
- **definition:** EarthCoop's internal monetary unit used within its participation/economic systems; its core existence/use is stable while detailed creation, activation, valuation and other rules remain governed by evolving economic rules.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** ECON-018 and following; EX-042/043; naming document.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** Bahar is the internal EarthCoop monetary unit and may be explained at the stable conceptual level.
- **prohibited_or_unverified_equivalences:** Do not call Bahar cryptocurrency, fiat currency, stablecoin, community currency or complementary currency as an exact synonym without criteria-level verification; do not publicize evolving parameters as permanent.

### `EXT-COMPLEMENTARY-CURRENCY` — پول مکمل / ارز مکمل

- **official_fa_name:** پول مکمل / ارز مکمل
- **english_working_label:** complementary currency
- **definition:** External monetary-system family that may help users understand or compare non-sovereign/internal monetary designs.
- **stability:** `stable`
- **source_evidence:** external monetary literature to be captured in Task 3; EarthCoop Bahar rules for comparison.
- **relation_to_earthcoop:** `comparison`
- **allowed_public_claims:** Use in comparison/explainer content if differences and similarities are explicit.
- **prohibited_or_unverified_equivalences:** Do not say «بهار یک پول مکمل است» as an established exact classification before verification.

### `EXT-COMMUNITY-CURRENCY` — پول اجتماعی / community currency

- **official_fa_name:** پول اجتماعی / پول جامعه‌محور
- **english_working_label:** community currency
- **definition:** External family of community-oriented exchange/monetary systems.
- **stability:** `stable`
- **source_evidence:** external literature to be captured in Task 3.
- **relation_to_earthcoop:** `comparison`
- **allowed_public_claims:** Comparison/education only.
- **prohibited_or_unverified_equivalences:** Do not equate Bahar with community currency merely because both are nonstandard/internal monetary concepts.

---

## G. Technology and digital governance

### `EC-NAJM-HODA` — نجم هدا

- **official_fa_name:** نجم هدا
- **english_working_label:** Najm Hoda intelligent management system
- **definition:** Named EarthCoop system whose name expands from «نرم‌افزار جامع مدیریت هوشمند دنیای ارثکوپ»; positioned within EarthCoop's digital-governance/assistance architecture.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** naming document; DG provisions referring to Najm Hoda, education, AI, data and accountable technology.
- **relation_to_earthcoop:** `exact`
- **allowed_public_claims:** The name/role can be explained at a high level; detailed capabilities must match implemented/documented behavior at publication time.
- **prohibited_or_unverified_equivalences:** Do not advertise capabilities not yet implemented; do not call AI an autonomous final decision-maker.

### `EC-DIGITAL-GOVERNANCE` — حکمرانی دیجیتال

- **official_fa_name:** حکمرانی دیجیتال EarthCoop
- **english_working_label:** digital governance
- **definition:** Governance of identity, data, privacy, online voting, security, AI, APIs, smart contracts and software infrastructure under justice, human dignity, participation, transparency, security and auditability constraints.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** DG scope and closing principles; DG is currently a formal draft for review/completion, so mechanics inside it may evolve.
- **relation_to_earthcoop:** `exact` as a subject/domain; detailed rules are evolving.
- **allowed_public_claims:** Explain principles and stable system domains; clearly distinguish draft technical/legal details.
- **prohibited_or_unverified_equivalences:** Do not present draft DG parameters/procedures as fully ratified permanent rules.

### `EXT-CIVIC-TECH` — فناوری مدنی / Civic Tech

- **official_fa_name:** فناوری مدنی
- **english_working_label:** civic tech
- **definition:** External category of technology enabling civic participation/governance, useful for comparison/discovery.
- **stability:** `stable`
- **source_evidence:** external literature to be captured in Task 3; EarthCoop participatory digital architecture.
- **relation_to_earthcoop:** `adjacent`
- **allowed_public_claims:** EarthCoop can be discussed in relation to civic-tech principles/functions where they overlap.
- **prohibited_or_unverified_equivalences:** Do not reduce all EarthCoop technology to civic tech; financial/cooperative/economic functions extend beyond the category.

---

## H. Project finance and economic participation

### `EC-PARTICIPATORY-INVESTMENT` — سرمایه‌گذاری و تأمین مالی مشارکتی

- **official_fa_name:** سرمایه‌گذاری مردمی / تأمین مالی مشارکتی (working public cluster)
- **english_working_label:** participatory investment / project finance
- **definition:** EarthCoop economic systems include public/private projects, project funds, investment and transparent/accountable flows; exact instruments/terminology depend on stabilized rules.
- **stability:** `stable-core-evolving-details`
- **source_evidence:** ECON-002/003; ECON Najm-Bahar/project/fund sections.
- **relation_to_earthcoop:** `exact` at broad project/investment level; specific external financing labels may be adjacent/comparison.
- **allowed_public_claims:** EarthCoop supports project-based investment and participatory/public funding mechanisms as defined by its economic rules.
- **prohibited_or_unverified_equivalences:** Do not automatically call every mechanism crowdfunding, securities issuance, tokenization, or regulated investment product.

---

## I. Stability gate summary

### Safe candidates for public SEO at core-definition level

- `EC-BRAND`
- `EC-JUSTICE`
- `EC-FUNDAMENTAL-RIGHTS`
- `EC-DIGNITY`
- `EC-RESPONSIBLE-FREEDOM`
- `EC-FUNDAMENTAL-EQUALITY`
- `EC-COMMON-RIGHT-EARTH`
- `EC-PRIVATE-LABOR-OWNERSHIP`
- `EC-PARTICIPATION-OVERSIGHT`
- `EC-PEOPLES-FREE-ECONOMY` (core model; evolving implementation details gated)
- `EC-PEOPLES-ECONOMY`
- `EC-GLASS-ECONOMY` (core definition; implementation details gated)

### Safe only with explicit evolving-details language

- `EC-GLOBAL-COOP`
- `EC-DIGITAL-COOP`
- `EC-COMMON-OWNERSHIP-RIGHT`
- `EC-DEMOCRATIC-GOVERNANCE`
- `EC-CONTINUOUS-ELECTIONS`
- `EC-LOCAL-TO-GLOBAL`
- `EC-NAJM-BAHAR`
- `EC-BAHAR`
- `EC-NAJM-HODA`
- `EC-DIGITAL-GOVERNANCE`
- `EC-PARTICIPATORY-INVESTMENT`

### External concepts that must remain comparison/adjacent until research proves stronger mapping

- `EXT-PLATFORM-COOP` — `comparison`
- `EXT-NATURAL-RIGHTS` — `comparison`
- `EXT-COMMONS` — `adjacent`
- `EXT-LIQUID-DEMOCRACY` — `comparison`
- `EXT-FREE-MARKET` — `comparison`
- `EXT-COMPLEMENTARY-CURRENCY` — `comparison`
- `EXT-COMMUNITY-CURRENCY` — `comparison`
- `EXT-CIVIC-TECH` — `adjacent`

No external concept above is currently authorized as an exact synonym for an EarthCoop proprietary concept.

## Self-check against approved design

Seed-family coverage is present for identity/cooperation, governance/elections, local-to-global architecture, People's Free Economy, People's Economy, Glass Economy, land/commons/ownership, Bahar/Najm-Bahar, Najm Hoda/digital governance, justice/rights/freedom/equality/dignity, participation/oversight, and project investment.

No entity marked `stable` relies solely on an unsupported equivalence with an external concept. Proprietary concepts with evolving parameters are deliberately marked `stable-core-evolving-details` rather than fully `stable`.
