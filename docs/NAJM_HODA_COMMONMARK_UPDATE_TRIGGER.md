# CommonMark security resolution trigger

This file exists only to trigger the controlled CommonMark dependency updater on the hardening branch.

Goals:

- identify the parent constraint introducing `league/commonmark`;
- resolve to a patched compatible version using Composer;
- never hand-edit `composer.lock`;
- keep all changes isolated to `agent/najm-hoda-hardening`;
- require the full hardening CI before accepting the dependency update.
