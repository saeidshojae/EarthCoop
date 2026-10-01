# Communication Center — Production Operations Runbook

This runbook is the operational contract for EarthCoop outbound email after the Communication Center migration.

## 1. Canonical delivery boundary

Production callers must create a canonical `Communication` through `CommunicationDispatcher`. Direct Laravel `Mail::*` provider access is allowed only inside `app/Services/Communication/EmailDeliveryAdapter.php`.

The architecture test `tests/Architecture/CommunicationDeliveryBoundaryTest.php` enforces this boundary.

## 2. Queue classes and worker priority

The dispatcher uses three queues:

1. `communications-critical` — all `required` communications, including authentication/security email.
2. `communications-normal` — normal operational and optional traffic.
3. `communications-bulk` — explicitly bulk traffic (`delivery_class=bulk`).

Run workers with critical traffic first. A production worker command may be configured as:

```bash
php artisan queue:work --queue=communications-critical,communications-normal,communications-bulk --tries=4 --timeout=120
```

For higher-volume deployments, use separate supervised worker pools and reserve capacity for `communications-critical` so bulk sends cannot starve authentication email.

Do not run a bulk-only worker as the sole communications worker.

## 3. Scheduler

Laravel's scheduler must run continuously. The application schedules:

```text
communications:process-due
```

every minute with `withoutOverlapping()`.

Server cron:

```cron
* * * * * cd /path/to/earthcoop && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled communications will not become eligible for delivery if the scheduler is stopped.

## 4. Retry and failure semantics

`DeliverCommunicationRecipient` owns provider delivery. It currently allows four attempts with backoff:

- retry 1: 60 seconds
- retry 2: 300 seconds
- retry 3: 900 seconds

Transient failures are re-thrown so the queue retries them. Permanent failures are recorded immediately as failed and are not retried by business code. After final queue exhaustion, `failed()` marks the recipient failed and aggregate communication status is refreshed.

Every delivery attempt is persisted in the communication attempt ledger. Do not manually rewrite recipient/attempt states to make a failed message appear sent.

Operational response to failure:

1. inspect the recipient's attempt history and failure classification;
2. fix provider/configuration/DNS problems first;
3. retry through Laravel's normal failed-job/retry tooling when the failure was transient and the job exists there;
4. for a new logical send, dispatch a new canonical communication with an intentional deduplication key rather than editing historical rows.

## 5. Sender identities and mail-provider environment

Canonical sender addresses and display names live in `communication_sender_identities`; templates point to an immutable template version and sender identity. The initial operational identities include management and support.

The provider transport itself remains Laravel Mail configuration. Production must supply the normal mail environment required by the selected Laravel mailer, for example the relevant values among:

```text
MAIL_MAILER
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_ENCRYPTION
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
```

Never commit provider passwords/API keys to the repository. After changing production mail environment, clear/rebuild Laravel configuration cache according to the normal deployment process and perform a controlled delivery check.

Sender identity records do not replace provider authentication: the provider must be authorised to send the configured EarthCoop domains/addresses.

## 6. SPF, DKIM and DMARC

Before enabling production sending for an EarthCoop domain:

- publish an SPF record authorising the actual provider(s);
- enable DKIM signing at the provider and publish its selector records;
- publish DMARC for the organisational domain, starting with monitored policy if necessary and tightening only after alignment is verified;
- verify that the visible From domain aligns with SPF and/or DKIM as required by DMARC;
- keep management/support/reply-to addresses valid and monitored where replies are expected.

Changing providers requires rechecking all three controls before switching production traffic.

## 7. Required/authentication traffic

Verification codes, password-reset codes, invitation issuance and invitation rejection are classified `required`. Preference/marketing suppression must never prevent these security/service emails from being queued solely because a user opted out of optional communication.

Do not reuse an old logical communication for a newly generated one-time code. Each newly generated code must have its own canonical communication/context snapshot.

## 8. Rollback and incident containment

A schema/template rollback must not delete audit history that has already been used for real deliveries. In production incidents, prefer application rollback plus containment of new dispatches over destructive deletion of communication/recipient/attempt rows.

If a release introduces delivery problems:

1. stop or scale down communications workers only if continued delivery is harmful;
2. preserve database records and failed jobs for diagnosis;
3. roll back application code using the normal deployment procedure;
4. ensure migrations are reversible before running any rollback command;
5. restart scheduler/workers only after configuration and provider health are confirmed;
6. validate one critical email and one normal email before restoring bulk throughput.

Never use destructive database reset/fresh commands on production.

## 9. Deployment checklist

Before release:

- migrations apply successfully;
- `CommunicationDeliveryBoundaryTest` is green;
- Communication feature/unit tests are green;
- authentication-sensitive email regressions are green;
- neighbouring registration/support/election regressions are green;
- full project validation is green;
- scheduler is running;
- queue workers listen to all three communication queues in the correct priority order;
- provider credentials are present only in environment/secret storage;
- SPF/DKIM/DMARC are valid for the active sender domain.

After release, perform controlled smoke checks for verification, password reset, support reply and an ordinary operational/admin message, and confirm their canonical communication/recipient/attempt records.