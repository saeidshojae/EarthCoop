# Laravel 12 Upgrade Resolver Trigger

This marker triggers the controlled Laravel 11 -> 12 Composer resolution on `agent/najm-hoda-hardening`.

Laravel 12 is the target supported framework line. The resolver performs a clean vendor install, resolves with Composer scripts disabled during lockfile generation, audits production dependencies, and commits only Composer-generated dependency changes after a valid install.

Retry: align `nunomaduro/collision` to `^8.9.5` and `phpunit/phpunit` to `^11.5.50` with Laravel `^12.0` before resolving the dependency graph.
