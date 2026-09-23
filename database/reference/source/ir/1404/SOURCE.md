# Iran 1404 administrative source — QUARANTINED INPUT (not an importable dataset)

Upstream repository: `Hameds/IranCountryDivisions`, commit `68687cf96cc1852d5d38c7283353c80829331758` (v3.0.0).  
Original source file: `data/1404/iran.csv`, upstream Git blob `ca9f4a0d69c7c9d77e6434447c7fe123a322271e`.  
Original metadata: `data/1404/metadata.json`; original source reported as Iranian Statistical Center annual workbook `source/1404/geo_1404.xlsx`.  
License: MIT, upstream copyright Hamed Saeedi; copy retained verbatim in `LICENSE.MIT.txt`. Verify the license obligations before distributing derived files.

The upstream CSV is split into **nine sequential, byte-preserving chunks** `iran.part-01.csv` … `iran.part-09.csv` to keep repository upload operations bounded. Concatenate their **raw bytes in filename order**, with no extra separators or BOM, to obtain the exact original CSV. The first chunk contains the one header row; the other chunks contain continuing records. The assembled text was independently compared character-for-character against the pinned upstream Git blob through the GitHub connector. The Git blob SHA is the content-integrity check; compute an independent SHA256 when running locally.

Source columns: `Id,ParentCountryDivisionId,Name,Code,DivisionType`. Use an RFC4180-compatible CSV parser (`csv.reader` or `fgetcsv`); at least one `Name` contains a quoted comma. Do not split rows by comma.

Verified against the **actual source CSV** (not merely README claims): 105,475 rows, 1 root, 31 provinces, 484 counties, 1,193 districts/sections, 2,777 rural districts, 1,481 cities, 99,317 settlements, and 191 urban zones. No duplicate `Id`, missing parent, or forbidden parent→type relation was found. Every parent appears before its child. Source metadata counts agree.

**Hard stop:** This is a SOURCE SNAPSHOT ONLY, under `database/reference/source/`, deliberately outside `database/reference/ir/<version>/`. It cannot be passed directly to `location:reference-import`. Do not copy/rename it into the live `v1` directory, do not run legacy seeders, and do not create election/governance groups for all 99,317 settlements. At least thousands of settlement names suggest farms/industrial facilities/barracks; source `DivisionType=6` does not resolve whether every row is a residential village.

**Sari qualification:** Upstream CSV contains only three urban-zone rows under city Sari (`ساری 1/2/3`), while our local UAT used Sari region 5. Do not automatically delete, override, or supersede live municipal/approved regions based on this snapshot. City zones, neighborhoods and streets require an independently validated municipal source.

**Cross-version identity:** `Id` is not stable between years. Persist source row ID, code, type, complete parent lineage, pinned source/version and deterministic staging key. Reconciliation with `v1`, pending proposals and existing residences is a separately reviewed operation.

Read the analysis and gated implementation plan in `docs/location-governance/IR_REAL_REFERENCE_DATA_RECOVERY_2026-09-23.md`. No Production or local application database was touched by this staging step.
