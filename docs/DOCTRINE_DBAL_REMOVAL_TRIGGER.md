# Doctrine DBAL Removal Resolver Trigger

This marker triggers a controlled Composer resolution that removes the direct `doctrine/dbal` requirement from `agent/najm-hoda-hardening`.

The resolver proved that both `doctrine/dbal` and its abandoned `doctrine/cache` chain can be removed cleanly. Composer removed five packages from the legacy chain, completed a clean install, and retained a clean production security audit.

Resolved dependency commit: `4760920` (`deps: remove unused Doctrine DBAL legacy chain`).

This documentation update intentionally triggers the full Najm Hoda hardening CI on the committed dependency graph.
