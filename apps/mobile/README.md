# EarthCoop Mobile

Official Flutter Android/iOS client for EarthCoop.

## Pinned M6 toolchain

- Flutter: `3.47.5` stable
- Dart: `3.13.4`
- Android minimum API: `24`
- iOS minimum deployment target: `15.0`

The mobile app is an API-first client of EarthCoop `/api/v1`. Laravel remains the sole authority for business rules, authorization, governance, elections, Najm Hoda and Najm Bahar.

Do not silently upgrade Flutter in CI or local release builds. Toolchain upgrades are explicit maintenance changes with analyze/test/build verification.
