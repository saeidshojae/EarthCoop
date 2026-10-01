<?php

namespace App\Console\Commands;

use App\Services\Deployment\VendorPackageInstaller;
use Illuminate\Console\Command;

class VendorPackageStatus extends Command
{
    protected $signature = 'deployment:vendor-package-status';

    protected $description = 'Show sanitized pending and installed vendor package state';

    public function handle(VendorPackageInstaller $installer): int
    {
        $this->line(json_encode(
            $installer->status(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));

        return self::SUCCESS;
    }
}
