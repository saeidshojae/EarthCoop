<?php

namespace Tests\Unit\Deployment;

use App\Services\Deployment\VendorPackageInstaller;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;
use ZipArchive;

class VendorPackageInstallerTest extends TestCase
{
    private string $root;
    private string $packages;
    private string $staging;
    private string $liveVendor;
    private string $lockPath;
    private string $installedState;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/vendor-package-'.bin2hex(random_bytes(4)));
        $this->packages = $this->root.'/packages';
        $this->staging = $this->root.'/staging';
        $this->liveVendor = $this->root.'/app/vendor';
        $this->lockPath = $this->root.'/app/composer.lock';
        $this->installedState = $this->packages.'/installed.json';

        @mkdir($this->packages, 0777, true);
        @mkdir($this->liveVendor, 0777, true);
        file_put_contents($this->lockPath, '{"packages":[]}');
        file_put_contents($this->liveVendor.'/old-marker.txt', 'old');

        config()->set('deployment-console.vendor_package_directory', $this->packages);
        config()->set('deployment-console.vendor_manifest', $this->packages.'/manifest.json');
        config()->set('deployment-console.vendor_installed_state', $this->installedState);
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

    public function test_install_rejects_missing_manifest_without_touching_live_vendor(): void
    {
        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
        $this->assertFileDoesNotExist($this->installedState);
    }

    public function test_install_rejects_package_checksum_mismatch(): void
    {
        $this->writeValidPackage(['vendor/autoload.php' => '<?php return new stdClass;', 'vendor/composer/installed.json' => '{}'], 'wrong');

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
        $this->assertFileDoesNotExist($this->installedState);
    }

    public function test_install_rejects_composer_lock_hash_mismatch(): void
    {
        $this->writeValidPackage(['vendor/autoload.php' => '<?php return new stdClass;', 'vendor/composer/installed.json' => '{}'], null, str_repeat('a', 64));

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
    }

    public function test_install_rejects_path_traversal_before_extraction(): void
    {
        $this->writeValidPackage([
            '../escape.php' => 'bad',
            'vendor/autoload.php' => '<?php return new stdClass;',
            'vendor/composer/installed.json' => '{}',
        ]);

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileDoesNotExist($this->root.'/escape.php');
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
    }

    public function test_install_rejects_absolute_and_backslash_traversal_entries(): void
    {
        foreach (['/absolute.php', 'vendor/..\\..\\escape.php'] as $unsafe) {
            $this->resetPackageDirectory();
            $this->writeValidPackage([
                $unsafe => 'bad',
                'vendor/autoload.php' => '<?php return new stdClass;',
                'vendor/composer/installed.json' => '{}',
            ]);

            $result = app(VendorPackageInstaller::class)->install();
            $this->assertFalse($result['success'], $unsafe);
            $this->assertFileExists($this->liveVendor.'/old-marker.txt');
        }
    }

    public function test_successful_install_replaces_entire_vendor_tree_and_writes_installed_state(): void
    {
        $this->writeValidPackage([
            'vendor/autoload.php' => '<?php return new \\Composer\\Autoload\\ClassLoader();',
            'vendor/composer/installed.json' => '{}',
            'vendor/new-marker.txt' => 'new',
        ]);

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertFileExists($this->liveVendor.'/new-marker.txt');
        $this->assertFileDoesNotExist($this->liveVendor.'/old-marker.txt');
        $this->assertFileExists($this->installedState);

        $state = json_decode(file_get_contents($this->installedState), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(hash_file('sha256', $this->lockPath), $state['composer_lock_sha256']);
        $this->assertSame('deadbeef', $state['source_git_sha']);
        $this->assertArrayHasKey('installed_at', $state);
    }

    public function test_status_distinguishes_pending_package_from_installed_package(): void
    {
        $this->writeValidPackage([
            'vendor/autoload.php' => '<?php return new \\Composer\\Autoload\\ClassLoader();',
            'vendor/composer/installed.json' => '{}',
        ]);

        $before = app(VendorPackageInstaller::class)->status();
        $this->assertTrue($before['pending']['available']);
        $this->assertFalse($before['installed']['available']);

        app(VendorPackageInstaller::class)->install();
        $after = app(VendorPackageInstaller::class)->status();

        $this->assertTrue($after['pending']['available']);
        $this->assertTrue($after['installed']['available']);
        $this->assertTrue($after['current_lock_matches_installed']);
    }

    public function test_missing_autoload_rejects_staged_vendor(): void
    {
        $this->writeValidPackage(['vendor/composer/installed.json' => '{}']);

        $result = app(VendorPackageInstaller::class)->install();

        $this->assertFalse($result['success']);
        $this->assertFileExists($this->liveVendor.'/old-marker.txt');
    }

    private function writeValidPackage(array $entries, ?string $forcedPackageHash = null, ?string $forcedLockHash = null): void
    {
        $lockHash = $forcedLockHash ?? hash_file('sha256', $this->lockPath);
        $filename = 'vendor-'.$lockHash.'.zip';
        $path = $this->packages.'/'.$filename;

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $manifest = [
            'schema_version' => 1,
            'package_filename' => $filename,
            'package_sha256' => $forcedPackageHash ?? hash_file('sha256', $path),
            'composer_lock_sha256' => $lockHash,
            'source_git_sha' => 'deadbeef',
            'created_at' => now()->toIso8601String(),
            'expected_top_level' => 'vendor',
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        ];

        file_put_contents($this->packages.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    private function resetPackageDirectory(): void
    {
        (new Filesystem())->deleteDirectory($this->packages);
        @mkdir($this->packages, 0777, true);
    }
}
