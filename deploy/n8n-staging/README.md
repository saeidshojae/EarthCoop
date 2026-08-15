# n8n staging deployment package

This directory is a staging-only deployment package for the Najm Hoda ↔ n8n integration. It does not enable production automation.

## VPS prerequisites

- Ubuntu 24.04 LTS (recommended)
- root/sudo access
- Docker Engine + Docker Compose plugin
- DNS A/AAAA record for the staging n8n hostname pointing to the VPS
- inbound TCP 80/443 allowed
- outbound HTTPS allowed
- backups for Docker volumes before upgrades

Do **not** expose PostgreSQL, Redis, or n8n port 5678 directly to the public internet. Only Caddy publishes ports 80/443.

## First boot

1. Copy `.env.example` to `.env` on the VPS.
2. Replace every `CHANGE_ME` value and every example hostname.
3. Generate secrets on the VPS, for example:
   - `openssl rand -hex 32` for the shared HMAC secret
   - `openssl rand -hex 32` or stronger for database/Redis credentials
   - `openssl rand -hex 32` or stronger for `N8N_ENCRYPTION_KEY`
4. Set restrictive permissions: `chmod 600 .env`.
5. Validate: `docker compose config`.
6. Start: `docker compose up -d`.
7. Inspect: `docker compose ps` and `docker compose logs --tail=100 n8n caddy`.
8. Confirm HTTPS works before configuring EarthCoop.

## EarthCoop staging values

On the EarthCoop staging host, configure the Laravel variables documented in `docs/NAJM_HODA_N8N_STAGING_RUNBOOK.md`. Use the same `NAJM_HODA_N8N_SHARED_SECRET` as the n8n staging stack. Keep `NAJM_HODA_N8N_CALLBACK_HTTP_ENABLED=false` initially.

Important: Redis in this Compose stack is private to the VPS. If EarthCoop remains on a separate shared-hosting server, do **not** expose this Redis instance publicly just to satisfy Laravel replay protection. Use a persistent cache available safely to EarthCoop (for example its database cache driver if supported and verified), or later move the staging application into a private network with Redis. The callback ingress must remain disabled until the application's readiness check reports an acceptable persistent cache.

## Upgrade policy

The Compose file intentionally centralizes the n8n image declaration. Before production use, pin n8n to a tested release instead of relying on `latest`, run the full staging GameDay, then upgrade deliberately. Never auto-upgrade the production integration without CI/staging evidence.

## Emergency stop

`docker compose stop n8n`

Then disable both n8n flags in the EarthCoop staging environment and clear Laravel configuration cache. Stored callback receipts remain audit evidence only.
