# FCM readiness implementation plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan inline with RED/GREEN evidence.

**Goal:** Let the existing authenticated deployment console verify FCM credentials and project authorization without a phone or notification delivery.

**Architecture:** A small FcmReadinessCheck service validates the private credential and performs one OAuth exchange and one HTTP v1 validate_only request to a fixed topic. A fixed Artisan command prints only categorical results; the existing console adds one allowlisted read-only operation.

**Tech Stack:** Laravel/PHP 8.2, existing Google Auth and Guzzle dependencies, PHPUnit, GitHub Actions.

**Spec:** docs/FIREBASE_FCM_READINESS_DESIGN.md

## Global constraints

- No credential contents, access tokens, exception text or response bodies in output/logs.
- No notification delivery, database writes, migration, dependency update or driver enablement.
- Explicit HTTP timeouts: connect 5 seconds, total 10 seconds, no redirects.
- Keep existing console authentication, secret, RBAC and throttle gates.

## Review focus

- Wrong project or credential identity must stop before OAuth/network.
- Malformed/missing/private credential must fail without leaking contents.
- OAuth failure or thrown exception must produce a safe status.
- Every provider request must carry validate_only=true and a fixed topic.
- Provider denial/network failure must never be reported as ready.

## Task 1: Server diagnostic

Files: app/Services/Push/FcmReadinessCheck.php; app/Console/Commands/FcmReadiness.php; app/Services/Push/FcmAccessTokenProvider.php; app/Services/Deployment/DeploymentConsoleService.php; routes/deployment-console.php; tests/Unit/Services/Push/FcmReadinessCheckTest.php; tests/Feature/Admin/DeploymentFcmReadinessTest.php.

Interface: FcmReadinessCheck(FcmAccessTokenProvider $tokens, ?string $projectId, ?string $credentialsPath)::check(): array with ready bool and categorical code string. Command deployment:fcm-readiness returns 0 only for a successful validation; no arbitrary arguments.

- [ ] Write tests for accepted validate-only request, absent credentials, mismatched project, unsafe token URI, malformed JSON, OAuth failure, provider 401/403/429/5xx and network exception; assert sanitized result and no requests on preflight failure.
- [ ] Run focused PHPUnit in isolated remote CI; expect missing diagnostic class/operation before implementation.
- [ ] Implement service, command and fixed console entry, with bounded OAuth HTTP handler.
- [ ] Run existing push and deployment-console contracts plus new tests; expect all pass and Pint on changed PHP files.
- [ ] Review whole diff, fix material findings, record receipts and create main-based PR. Deploy only verified server changes; actual host diagnostic acceptance remains separate.
