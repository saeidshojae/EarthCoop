# EarthCoop Mobile Hybrid Native/Web Architecture

Status: Accepted direction; broad implementation deferred until after M6 Task 13 physical-device acceptance.

## Decision

EarthCoop mobile will remain a real Flutter native client for the product core. Existing EarthCoop web views may be reused through a constrained in-app WebView bridge for lower-frequency areas, then migrated to native Flutter incrementally when product value justifies it.

This is not a decision to wrap the whole website in a WebView and it does not replace the existing native foundation.

## Native core

The following remain native-first because they benefit materially from device integration, offline behavior, typed navigation, or frequent interaction:

- bootstrap, login, native session and secure credential storage;
- home and primary mobile navigation;
- My Groups and group detail/core interaction surfaces;
- notifications and typed deep links;
- chat and high-frequency group interaction;
- offline queue/replay and reconnect handling;
- media picking/upload and progress;
- push registration/open handling for FCM and HMS;
- core profile/device settings where native integration matters.

## WebView bridge candidates

Existing responsive web views may initially be surfaced through the bridge when rebuilding them in Flutter would duplicate substantial UI with little immediate mobile-specific value. Candidate categories include:

- statutes, policies, regulations and documentation;
- lower-frequency informational and administrative screens;
- mature web forms that are already mobile responsive;
- modules not yet selected for native-first product investment.

A feature is not automatically a WebView candidate merely because a web page already exists. Authentication, offline requirements, push/deep-link ownership, upload behavior and mobile interaction quality must be reviewed first.

## Navigation ownership

Flutter owns the app-level navigation stack. A user moving from a native page to an approved WebView destination must still have predictable Android/iOS Back behavior and a visible in-app back affordance when appropriate.

Typed native deep links remain allowlisted and may not be replaced by arbitrary URL navigation. External/untrusted links should leave the trusted in-app surface rather than gaining unrestricted WebView navigation.

## Security boundary

The WebView bridge must use an explicit trusted-origin allowlist. It must not expose long-lived bearer tokens in URLs or inject broad privileged JavaScript bridges into arbitrary pages.

If authenticated web handoff is needed, design it as a backend-mediated, short-lived/one-time handoff rather than copying native credentials into a URL. The exact handoff contract is a separate implementation task and requires its own threat review and tests.

## Offline and native guarantees

Offline queue/replay guarantees apply to the native core only unless a WebView feature explicitly implements and tests equivalent behavior. A WebView page may show an online-required state rather than pretending to participate in the native offline queue.

## Migration rule

WebView is a bridge, not the permanent default. A bridged feature should move to native Flutter when one or more of these become true:

- it becomes high-frequency or central to the mobile journey;
- it needs offline mutation/replay;
- it needs deep device integration or richer media flows;
- its WebView UX is materially worse than the native shell;
- maintaining the web/native boundary becomes more expensive than a native implementation.

## Task boundary

M6 Task 13 is responsible for proving the native foundation and closing physical-device blockers. It records this architecture decision but does not expand into rebuilding or bridging the full EarthCoop web product.

After Task 13 closes, a separate Hybrid Navigation/WebView Integration task will inventory existing EarthCoop views, classify each as native/core, bridge candidate, or web-only/admin, and implement the smallest secure bridge needed for the first mobile release.
