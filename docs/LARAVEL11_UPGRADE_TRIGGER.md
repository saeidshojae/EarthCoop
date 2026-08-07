# Laravel 11 Upgrade Resolver Trigger

This marker triggers the controlled Laravel 10 -> 11 Composer resolution on `agent/najm-hoda-hardening`.

Laravel 11 remains an intermediate compatibility stepping stone toward Laravel 12. The resolver performs a clean vendor install and only commits Composer-generated dependency changes after a valid resolution.

Retry: align `nunomaduro/collision` to `^8.1` and `phpunit/phpunit` to `^10.5`, resolve the lockfile with Composer scripts disabled, then run scripts only after the clean vendor install.
