# Laravel 12 Upgrade Resolver Trigger

This marker triggers the controlled Laravel 11 -> 12 Composer resolution on `agent/najm-hoda-hardening`.

Laravel 12 is the target supported framework line. The resolver performs a clean vendor install, resolves with Composer scripts disabled during lockfile generation, audits production dependencies, and commits only Composer-generated dependency changes after a valid install.
