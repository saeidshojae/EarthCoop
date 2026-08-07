# Doctrine DBAL Removal Resolver Trigger

This marker triggers a controlled Composer resolution that removes the direct `doctrine/dbal` requirement from `agent/najm-hoda-hardening`.

The resolver must prove that both `doctrine/dbal` and its abandoned `doctrine/cache` chain disappear, complete a clean Composer install, and retain a clean production security audit before any generated Composer changes are committed.
