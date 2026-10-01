<?php

namespace App\Services\Deployment;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;
use ZipArchive;

class VendorPackageInstaller
{
    private Filesystem $files;

    public function __construct(?Filesystem $files = null)
    {
        $this->files = $files ?? new Filesystem();
    }

    public function status(): array
    {
        $pending = $this->readJsonFile($this->manifestPath());
        $installed = $this->readJsonFile($this->installedStatePath());
        $currentLockHash = $this->currentLockHash();

        return [
            'zip_supported' => class_exists(ZipArchive::class),
            'pending' => $this->sanitizedState($pending),
            'installed' => $this->sanitizedState($installed),
            'current_lock_matches_pending' => is_array($pending)
                && isset($pending['composer_lock_sha256'])
                && hash_equals((string) $pending['composer_lock_sha256'], $currentLockHash),
            'current_lock_matches_installed' => is_array($installed)
                && isset($installed['composer_lock_sha256'])
                && hash_equals((string) $installed['composer_lock_sha256'], $currentLockHash),
        ];
    }

    public function install(): array
    {
        $backupPath = null;
        $liveActivated = false;
        $stagingRoot = null;

        try {
            if (! class_exists(ZipArchive::class)) {
                throw new RuntimeException('ZIP support is unavailable.');
            }

            $manifest = $this->loadAndValidateManifest();
            $lockHash = $manifest['composer_lock_sha256'];
            $packagePath = $this->packageDirectory().DIRECTORY_SEPARATOR.$manifest['package_filename'];

            if (! is_file($packagePath)) {
                throw new RuntimeException('Vendor package file is missing.');
            }

            $actualPackageHash = hash_file('sha256', $packagePath);
            if (! is_string($actualPackageHash) || ! hash_equals($manifest['package_sha256'], $actualPackageHash)) {
                throw new RuntimeException('Vendor package checksum mismatch.');
            }

            $currentLockHash = $this->currentLockHash();
            if (! hash_equals($lockHash, $currentLockHash)) {
                throw new RuntimeException('Composer lock checksum mismatch.');
            }

            $stagingRoot = rtrim($this->stagingDirectory(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$lockHash;
            $this->files->deleteDirectory($stagingRoot);
            $this->files->makeDirectory($stagingRoot, 0775, true, true);

            $zip = new ZipArchive();
            $openResult = $zip->open($packagePath);
            if ($openResult !== true) {
                throw new RuntimeException('Vendor package cannot be opened.');
            }

            try {
                $this->assertArchiveEntriesSafe($zip);
                if (! $zip->extractTo($stagingRoot)) {
                    throw new RuntimeException('Vendor package extraction failed.');
                }
            } finally {
                $zip->close();
            }

            $stagedVendor = $stagingRoot.DIRECTORY_SEPARATOR.'vendor';
            $this->assertStagedVendorValid($stagedVendor);
            $this->assertSameFilesystem($stagingRoot, $this->liveVendorDirectory());

            $liveVendor = $this->liveVendorDirectory();
            if (is_dir($liveVendor)) {
                $backupPath = dirname($liveVendor).DIRECTORY_SEPARATOR.'vendor.__previous_'.gmdate('YmdHis').'_'.bin2hex(random_bytes(3));
                if (! @rename($liveVendor, $backupPath)) {
                    throw new RuntimeException('Current vendor directory could not be moved to backup.');
                }
            }

            if (! @rename($stagedVendor, $liveVendor)) {
                if ($backupPath !== null && is_dir($backupPath)) {
                    @rename($backupPath, $liveVendor);
                }
                throw new RuntimeException('Staged vendor directory could not be activated.');
            }
            $liveActivated = true;

            $this->verifyActivatedVendor($liveVendor);
            $this->writeInstalledState($manifest);
            $this->cleanupBackups(dirname($liveVendor));
            $this->files->deleteDirectory($stagingRoot);

            return [
                'success' => true,
                'message' => 'Vendor package installed and verified.',
                'lock_hash' => $lockHash,
                'source_sha' => $manifest['source_git_sha'],
            ];
        } catch (Throwable $e) {
            if ($liveActivated) {
                $this->restorePreviousVendor($backupPath);
            }

            if (is_string($stagingRoot) && $stagingRoot !== '') {
                $this->files->deleteDirectory($stagingRoot);
            }

            return [
                'success' => false,
                'message' => $this->sanitizeFailureMessage($e->getMessage()),
                'lock_hash' => null,
                'source_sha' => null,
            ];
        }
    }

    private function loadAndValidateManifest(): array
    {
        $manifest = $this->readJsonFile($this->manifestPath());
        if (! is_array($manifest)) {
            throw new RuntimeException('Vendor package manifest is missing or invalid.');
        }

        $required = [
            'schema_version',
            'package_filename',
            'package_sha256',
            'composer_lock_sha256',
            'source_git_sha',
            'created_at',
            'expected_top_level',
            'php_version',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new RuntimeException('Vendor package manifest is incomplete.');
            }
        }

        if ((int) $manifest['schema_version'] !== 1) {
            throw new RuntimeException('Unsupported vendor package manifest version.');
        }

        foreach (['package_sha256', 'composer_lock_sha256'] as $hashKey) {
            if (! is_string($manifest[$hashKey]) || ! preg_match('/^[a-f0-9]{64}$/', $manifest[$hashKey])) {
                throw new RuntimeException('Vendor package manifest contains an invalid checksum.');
            }
        }

        $lockHash = (string) $manifest['composer_lock_sha256'];
        if (! is_string($manifest['package_filename']) || $manifest['package_filename'] !== 'vendor-'.$lockHash.'.zip') {
            throw new RuntimeException('Vendor package filename is invalid.');
        }

        if ($manifest['expected_top_level'] !== 'vendor') {
            throw new RuntimeException('Vendor package top-level directory is invalid.');
        }

        if (! is_string($manifest['source_git_sha']) || $manifest['source_git_sha'] === '') {
            throw new RuntimeException('Vendor package source commit is invalid.');
        }

        return $manifest;
    }

    private function assertArchiveEntriesSafe(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_string($name) || $name === '') {
                throw new RuntimeException('Vendor package contains an invalid archive entry.');
            }

            $normalized = str_replace('\\', '/', $name);
            $segments = explode('/', $normalized);

            if (str_starts_with($normalized, '/')
                || preg_match('/^[A-Za-z]:\//', $normalized)
                || str_starts_with($normalized, '//')
                || in_array('..', $segments, true)
                || ($normalized !== 'vendor' && ! str_starts_with($normalized, 'vendor/'))) {
                throw new RuntimeException('Vendor package contains an unsafe archive path.');
            }

            $opsys = 0;
            $attr = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attr)) {
                $mode = ($attr >> 16) & 0170000;
                if ($mode === 0120000) {
                    throw new RuntimeException('Vendor package symlinks are not allowed.');
                }
            }
        }
    }

    private function assertStagedVendorValid(string $stagedVendor): void
    {
        if (! is_file($stagedVendor.DIRECTORY_SEPARATOR.'autoload.php')) {
            throw new RuntimeException('Staged vendor autoload file is missing.');
        }

        $composerDir = $stagedVendor.DIRECTORY_SEPARATOR.'composer';
        if (! is_file($composerDir.DIRECTORY_SEPARATOR.'installed.php')
            && ! is_file($composerDir.DIRECTORY_SEPARATOR.'installed.json')) {
            throw new RuntimeException('Staged Composer metadata is missing.');
        }

        $entries = @scandir($stagedVendor);
        if (! is_array($entries) || count(array_diff($entries, ['.', '..'])) === 0) {
            throw new RuntimeException('Staged vendor directory is empty.');
        }
    }

    private function assertSameFilesystem(string $stagingRoot, string $liveVendor): void
    {
        $stagingStat = @stat($stagingRoot);
        $liveParent = dirname($liveVendor);
        $liveStat = @stat($liveParent);

        if (! is_array($stagingStat) || ! is_array($liveStat) || ($stagingStat['dev'] ?? null) !== ($liveStat['dev'] ?? null)) {
            throw new RuntimeException('Vendor staging and live paths are not on the same filesystem.');
        }
    }

    private function verifyActivatedVendor(string $liveVendor): void
    {
        $autoload = $liveVendor.DIRECTORY_SEPARATOR.'autoload.php';
        if (! is_file($autoload)) {
            throw new RuntimeException('Activated vendor autoload file is missing.');
        }

        $loader = require $autoload;
        if (! $loader instanceof ClassLoader) {
            throw new RuntimeException('Activated Composer autoloader failed verification.');
        }
    }

    private function restorePreviousVendor(?string $backupPath): void
    {
        $liveVendor = $this->liveVendorDirectory();

        if (is_dir($liveVendor)) {
            $this->files->deleteDirectory($liveVendor);
        }

        if (is_string($backupPath) && is_dir($backupPath)) {
            @rename($backupPath, $liveVendor);
        }
    }

    private function writeInstalledState(array $manifest): void
    {
        $state = [
            'schema_version' => 1,
            'package_filename' => $manifest['package_filename'],
            'package_sha256' => $manifest['package_sha256'],
            'composer_lock_sha256' => $manifest['composer_lock_sha256'],
            'source_git_sha' => $manifest['source_git_sha'],
            'created_at' => $manifest['created_at'],
            'php_version' => $manifest['php_version'],
            'installed_at' => now()->toIso8601String(),
        ];

        $path = $this->installedStatePath();
        $this->files->ensureDirectoryExists(dirname($path), 0775, true);
        $temporary = $path.'.tmp.'.bin2hex(random_bytes(4));
        file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Installed vendor state could not be persisted.');
        }
    }

    private function cleanupBackups(string $applicationDirectory): void
    {
        $retention = max(0, (int) config('deployment-console.vendor_backup_retention', 1));
        $backups = glob(rtrim($applicationDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'vendor.__previous_*') ?: [];
        usort($backups, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        foreach (array_slice($backups, $retention) as $backup) {
            if (is_dir($backup)) {
                $this->files->deleteDirectory($backup);
            }
        }
    }

    private function sanitizedState(?array $state): array
    {
        if (! is_array($state)) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'schema_version' => $state['schema_version'] ?? null,
            'package_filename' => $state['package_filename'] ?? null,
            'package_sha256' => $state['package_sha256'] ?? null,
            'composer_lock_sha256' => $state['composer_lock_sha256'] ?? null,
            'source_git_sha' => $state['source_git_sha'] ?? null,
            'created_at' => $state['created_at'] ?? null,
            'php_version' => $state['php_version'] ?? null,
            'installed_at' => $state['installed_at'] ?? null,
        ];
    }

    private function readJsonFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function currentLockHash(): string
    {
        $path = $this->composerLockPath();
        if (! is_file($path)) {
            throw new RuntimeException('Composer lock file is missing.');
        }

        $hash = hash_file('sha256', $path);
        if (! is_string($hash)) {
            throw new RuntimeException('Composer lock checksum could not be calculated.');
        }

        return $hash;
    }

    private function packageDirectory(): string
    {
        return (string) config('deployment-console.vendor_package_directory', storage_path('deployment/vendor-packages'));
    }

    private function manifestPath(): string
    {
        return (string) config('deployment-console.vendor_manifest', storage_path('deployment/vendor-packages/manifest.json'));
    }

    private function installedStatePath(): string
    {
        return (string) config('deployment-console.vendor_installed_state', storage_path('deployment/vendor-packages/installed.json'));
    }

    private function stagingDirectory(): string
    {
        return (string) config('deployment-console.vendor_staging_directory', storage_path('deployment/vendor-staging'));
    }

    private function liveVendorDirectory(): string
    {
        return (string) config('deployment-console.vendor_live_directory', base_path('vendor'));
    }

    private function composerLockPath(): string
    {
        return (string) config('deployment-console.composer_lock_path', base_path('composer.lock'));
    }

    private function sanitizeFailureMessage(string $message): string
    {
        return trim(str_replace([base_path(), storage_path()], ['[app]', '[storage]'], $message));
    }
}
