# Laravel 10 Upgrade Resolver Trigger

This marker triggers the controlled Laravel 9 -> 10 Composer resolution on `agent/najm-hoda-hardening`.

The target remains a supported Laravel major; Laravel 10 is only the first compatibility stepping stone toward Laravel 12.

Retry: resolve the lock file without mutating the tracked legacy vendor tree, then verify a clean dist install before committing dependency changes.
