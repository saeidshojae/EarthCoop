# Laravel 11 Upgrade Resolver Trigger

This marker triggers the controlled Laravel 10 -> 11 Composer resolution on `agent/najm-hoda-hardening`.

Laravel 11 remains an intermediate compatibility stepping stone toward Laravel 12. The resolver performs a clean vendor install and only commits Composer-generated dependency changes after a valid resolution.

Retry: align `nunomaduro/collision` with Laravel 11's `^8.1` requirement before resolving the dependency graph.
