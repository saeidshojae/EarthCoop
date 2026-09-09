# Reference Geography Importer — Implementation Notes

## Dataset version CLI option

The approved C4 plan originally illustrated the command as:

```bash
php artisan location:reference-import IR --version=v1 --dry-run
```

That spelling cannot be used safely in Laravel Artisan because Symfony Console reserves the global `--version` (`-V`) option. When present, the application prints the Laravel framework version and exits before the command handler is executed.

Therefore the C4 runtime contract uses the non-reserved option `--dataset-version`:

```bash
php artisan location:reference-import IR --dataset-version=v1 --dry-run
php artisan location:reference-import IR --dataset-version=v1 --apply
```

This is an implementation-level compatibility correction only. The architectural requirement remains unchanged: reference geography is explicitly versioned, dry-run is non-mutating, apply is explicit, and same source/version import is idempotent and auditable.
