# Najm Hoda ↔ n8n Staging Runbook

## Purpose

This runbook defines the first controlled staging activation of the Najm Hoda ↔ n8n integration. It does **not** authorize production autonomous execution. The first live workflow is `ops.health.read` and remains strictly `read_only`.

## Preconditions

- PR #8 hardening foundation and PR #9 chat/context stack are available in the staging build.
- PR #14 code is deployed to an isolated staging environment.
- `APP_ENV` is not production during first activation.
- n8n is reachable only through the intended staging network path.
- TLS is enabled for the n8n base URL.
- A shared persistent Laravel cache is configured. Redis is preferred. `file` and `array` are not acceptable for callback replay protection.
- Database migrations are current, including `najm_hoda_n8n_callbacks`.
- Callback ingress remains disabled until outbound health and secret verification pass.

## Required environment configuration

Provision values in the staging server secret/environment store. Never commit actual values.

```dotenv
CACHE_DRIVER=redis
NAJM_HODA_N8N_ENABLED=true
NAJM_HODA_N8N_BASE_URL=https://<staging-n8n-host>
NAJM_HODA_N8N_HEALTH_PATH=/healthz
NAJM_HODA_N8N_DISPATCH_PATH=/webhook/najm-hoda
NAJM_HODA_N8N_SHARED_SECRET=<32+ byte random secret>
NAJM_HODA_N8N_TIMEOUT_SECONDS=8
NAJM_HODA_N8N_CALLBACK_MAX_SKEW_SECONDS=300
NAJM_HODA_N8N_CALLBACK_REPLAY_TTL_SECONDS=900
NAJM_HODA_N8N_CALLBACK_REQUIRE_PERSISTENT_CACHE=true
NAJM_HODA_N8N_CALLBACK_HTTP_ENABLED=false
```

If Redis is unavailable, the `database` or `memcached` cache driver may be used only after shared/persistent behavior is verified. Do not enable callback ingress with `file` or `array` cache.

## Shared-secret provisioning and rotation

1. Generate a cryptographically random secret outside source control.
2. Store the same secret in the staging EarthCoop environment and the matching n8n credential/secret store.
3. Do not expose the secret in workflow output, browser code, logs, callback result data, or the future admin UI.
4. Verify signed outbound health/dispatch in staging.
5. For rotation, provision the new value on both sides during a controlled maintenance window, clear configuration cache, perform a health probe, and record the rotation timestamp in operational evidence. Do not retain old values in repository files.

## First workflow contract: `ops.health.read`

Mode: `read_only`.

The workflow must not modify EarthCoop, external services, credentials, files, users, messages, payments, projects, groups, or n8n configuration.

A completed callback result must be exactly shaped as:

```json
{
  "healthy": true,
  "observed_at": "2026-08-08T00:00:00+00:00",
  "checks": {
    "n8n.webhook": true,
    "n8n.database": true
  }
}
```

Rules:

- `healthy` is boolean.
- `observed_at` is a parseable timestamp.
- `checks` contains at most 20 boolean entries with tokenized names.
- unknown fields are rejected.
- command-shaped fields such as `execute`, `action`, `capability`, or arbitrary nested instructions are rejected because they are not part of the schema.

Progress callbacks use only:

```json
{"phase":"checking","percent":50}
```

Failed callbacks use only:

```json
{"error_code":"N8N_HEALTH_FAILED"}
```

## Activation sequence

### Phase 1 — outbound only

1. Keep `NAJM_HODA_N8N_CALLBACK_HTTP_ENABLED=false`.
2. Confirm Laravel cache driver is `redis`, `database`, or `memcached`.
3. Confirm `NAJM_HODA_N8N_ENABLED=true` and the n8n base URL/secret are present.
4. Run the signed n8n health check through the application service.
5. Confirm the corresponding runtime audit event is recorded and contains no secret.
6. Dispatch only `ops.health.read`.
7. Confirm n8n receives version, request ID, correlation ID, workflow, mode, actor ID, sent timestamp, and bounded payload.

### Phase 2 — callback loopback

1. Verify the staging callback URL is reachable only through the intended ingress path.
2. Confirm persistent shared cache operation from all application workers/instances.
3. Set `NAJM_HODA_N8N_CALLBACK_HTTP_ENABLED=true` in staging only.
4. Send one valid signed `progress` callback.
5. Send one valid signed `completed` callback using the canonical health schema.
6. Confirm exactly one durable DB receipt per request ID.
7. Confirm result is stored only and no capability/action execution occurs.

### Phase 3 — staging GameDay

Run and capture evidence for:

- invalid HMAC signature → rejected
- stale timestamp → rejected
- exact replay → rejected
- same request ID with changed status → durable idempotency guard blocks duplicate receipt
- callback flood above 30/minute from one source → rate limited
- n8n outage / connection failure → outbound dispatch fails closed
- cache unavailable or non-persistent → callback ingress unavailable
- n8n returns unexpected health result field → callback rejected by workflow contract

## Exit criteria for first staging milestone

All conditions must hold:

- code-level n8n CI is green
- staging outbound health succeeds with real n8n
- callback ingress works only with persistent shared cache
- health callback schema validation passes
- all staging GameDay failures are observed as expected
- no raw shared secret appears in logs, DB callback results, runtime events, browser responses, or admin surfaces
- no `apply` workflow exists in configuration
- no callback path can reach capability registry, execution service, action executor, or autonomous goal loop
- admin management requirements remain tracked in Issue #15 before routine operational use

## Rollback / emergency disable

Set both values to false and clear Laravel config cache:

```dotenv
NAJM_HODA_N8N_ENABLED=false
NAJM_HODA_N8N_CALLBACK_HTTP_ENABLED=false
```

Then verify:

- outbound gateway fails closed
- callback endpoint returns unavailable
- existing stored callback receipts remain audit evidence only
- no workflow is executed or retried automatically

## Production boundary

Completion of this staging runbook does not authorize production `apply` automation. Any approval-required or apply-capable workflow requires a separate milestone with explicit authorization, approval UI/process, idempotent execution semantics, cancellation, audit, and dedicated GameDay coverage.
