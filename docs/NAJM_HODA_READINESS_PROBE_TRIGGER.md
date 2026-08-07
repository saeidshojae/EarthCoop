# Najm Hoda Production Readiness Probe Trigger

This marker runs the existing `najm-hoda:production-readiness --strict` gate in an isolated CI environment after a clean Composer install and full database migration.

The purpose is diagnostic: expose the current governance, drift, runbook, approval, game-day, and compliance-evidence blockers before the readiness command is promoted into the main release gate.
