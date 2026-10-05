# FCM readiness design

The administrator has configured a private service-account file and FCM project on the host, but optimize_clear does not prove provider connectivity. Add a fixed read-only deployment-console operation fcm_readiness, backed by deployment:fcm-readiness. It runs with the configured project/file independently of PUSH_DELIVERY_DRIVER.

Validate readable JSON (64 KiB maximum), service_account type, exact matching project, matching IAM service-account email suffix, nonempty private key, expected token_uri https://oauth2.googleapis.com/token and private location outside public_path. Only then obtain an OAuth token via the existing token provider with a bounded Guzzle handler. Send HTTP v1 validate_only=true to fixed topic earthcoop-readiness-probe; never use a real device token. Google documents validate_only as request testing without delivery: https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages/send . HTTP success with the documented name field establishes validation acceptance; it is not mobile receipt.

Output is a ready boolean and one fixed categorical code. Missing/malformed/wrong-project credentials, OAuth absence/exception, provider rejection or transport exception must fail closed. No secret, private file path, provider response body or exception text is printed. Console RBAC, temporary secret and throttling remain unchanged. No new package/migration/driver enablement.

Execution ruling: use an isolated sibling worktree based on production main d1ecab0c494c8108a32908cfedf6854f435a055a; never mix the unmerged native foundation. PHP/Composer are absent locally, so PHPUnit evidence comes from isolated CI. Do not repeat or bypass the unavailable local runtime.
