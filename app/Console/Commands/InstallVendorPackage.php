<?php

namespace App\Console\Commands;

use App\Services\Deployment\VendorPackageInstaller;
use Illuminate\Console\Command;

class InstallVendorPackage extends Command
{
    protected $signature = 'deployment:install-vendor-package {--confirm=}';

    protected $description = 'Install the canonical verified vendor package staged for this deployment';

    public function handle(VendorPackageInstaller $installer): int
    {
        if ((string) $this->option('confirm') !== 'INSTALL-VENDOR-PACKAGE') {
            $this->error('Exact confirmation INSTALL-VENDOR-PACKAGE is required.');

            return self::FAILURE;
        }

        $result = $installer->install();

        if (! ($result['success'] ?? false)) {
            $this->error((string) ($result['message'] ?? 'Vendor package installation failed.'));

            return self::FAILURE;
        }

        $this->info((string) ($result['message'] ?? 'Vendor package installed.'));
        $this->line('composer_lock_sha256='.(string) ($result['lock_hash'] ?? ''));
        $this->line('source_git_sha='.(string) ($result['source_sha'] ?? ''));

        return self::SUCCESS;
    }
}
