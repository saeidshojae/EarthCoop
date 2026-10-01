<?php

namespace Tests\Unit\Deployment;

use App\Services\Deployment\VendorPackageInstaller;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;
use ZipArchive;

class VendorPackageInstallerRollbackTest extends TestCase
{
    private string $root;
    private string $packages;
    private string $staging;
    private string $liveVendor;
    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/vendor-rollback-'.bin2hex(random_bytes(4)));
        $this->packages = $this->root.'/packages';
        $this->staging = $this->root.'/staging';
        $this->liveVendor = $this->root.'/app/vendor';
        $this->lockPath = $this->root.'/app/composer.lock';

        @mkdir($this->packages, 0777, true);
        @mkdir($this->liveVendor, 0777, true);
        file_put_contents($this->lockPath, '{"packages":[]}');
        file_put_contents($this->liveVendor.'/old-marker.txt', 'old');

        config()->set('deployment-console.vendor_package_directory', $this->packages);
        config()->set('deployment-console.vendor_manifest', $this->packages.'/manifest.json');
        config()->set('deployment-console.vendor_installed_state', $this->packages.'/installed.json');
        config()->set('deployment-console.vendor_staging_directory', $this->staging);
        config()->set('deployment-console.vendor_live_directory', $this->liveVendor);
        config()->set('deployment-console.composer_lock_path', $this->lockPath);
        config()->set('deployment-console.vendor_backup_retention', 1);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_post_swap_bootstrap_failure_restores_previous_vendor(): void
    {
        $this->writePackage([
            'vendor/autoload.php' => '<?php return new stdClass();',
            'vendor/composer/installed.json' => '{}',
            'vendor/new-marker.txt' => 'new',
        ]);

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
        $this->assertFileDoesNotExist($this->liveVendor.'/new-marker.txt');
        $this->assertFileDoesNotExist($this->packages.'/installed.json');
    }

    public function test_successful_activation_prunes_older_backups_to_retention_count(): void
    {
        $appDir = dirname($this->liveVendor);
        @mkdir($appDir.'/vendor.__previous_20200101000000_old1', 0777, true);
        @mkdir($appDir.'/vendor.__previous_20210101000000_old2', 0777, true);
        file_put_contents($appDir.'/vendor.__previous_20200101000000_old1/marker', 'old1');
        file_put_contents($appDir.'/vendor.__previous_20210101000000_old2/marker', 'old2');
        touch($appDir.'/vendor.__previous_20200101000000_old1', 1577836800);
        touch($appDir.'/vendor.__previous_20210101000000_old2', 1609459200);

        $this->writePackage([
            'vendor/autoload.php' => '<?php return new \\Composer\\Autoload\\ClassLoader();',
            'vendor/composer/installed.json' => '{}',
        ]);

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $backups = glob($appDir.'/vendor.__previous_*') ?: [];
        $this->assertCount(1, $backups);
    }

    public function test_incomplete_manifest_fails_before_live_vendor_changes(): void
    {
        file_put_contents($this->packages.'/manifest.json', json_encode([
            'schema_version' => 1,
            'package_filename' => 'vendor-invalid.zip',
        ], JSON_THROW_ON_ERROR));

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
    }

    private function writePackage(array $entries): void
    {
        $lockHash = hash_file('sha256', $this->lockPath);
        $filename = 'vendor-'.$lockHash.'.zip';
        $path = $this->packages.'/'.$filename;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        file_put_contents($this->packages.'/manifest.json', json_encode([
            'schema_version' => 1,
            'package_filename' => $filename,
            'package_sha256' => hash_file('sha256', $path),
            'composer_lock_sha256' => $lockHash,
            'source_git_sha' => 'deadbeef',
            'created_at' => now()->toIso8601String(),
            'expected_top_level' => 'vendor',
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}
