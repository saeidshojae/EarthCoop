<?php

namespace Tests\Feature\Secretariat;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecretariatS9DisasterRecoveryDrillTest extends TestCase
{
    public function test_full_database_and_secretariat_storage_backup_restore_drill_passes(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Secretariat DR drill is authoritative on MySQL.');
        }

        $connection = config('database.connections.mysql');
        $output = storage_path('framework/testing/secretariat-dr-' . Str::uuid());
        $storage = storage_path('framework/testing/secretariat-storage-' . Str::uuid());
        mkdir($output, 0777, true);
        mkdir($storage, 0777, true);
        file_put_contents($storage . '/probe.txt', 'secretariat-dr-storage-probe');

        $process = new Process(['bash', base_path('scripts/secretariat-dr-drill.sh')], base_path(), [
            'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($connection['port'] ?? 3306),
            'DB_USERNAME' => (string) ($connection['username'] ?? 'root'),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
            'DB_DATABASE' => (string) ($connection['database'] ?? ''),
            'SECRETARIAT_STORAGE_DIR' => $storage,
            'SECRETARIAT_DR_OUTPUT' => $output,
            'KEEP_DRILL_DATABASE' => '0',
        ]);
        $process->setTimeout(120);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            "DR drill failed.\nSTDOUT:\n{$process->getOutput()}\nSTDERR:\n{$process->getErrorOutput()}"
        );
        $this->assertStringContainsString('Secretariat DR drill PASS', $process->getOutput());

        $manifests = glob($output . '/*/manifest.txt') ?: [];
        $verifications = glob($output . '/*/secretariat-row-count-verification.csv') ?: [];
        $storageArchives = glob($output . '/*/secretariat-storage.tar.gz') ?: [];

        $this->assertCount(1, $manifests);
        $this->assertCount(1, $verifications);
        $this->assertCount(1, $storageArchives);
        $this->assertStringContainsString(
            'backup_scope=full_database_plus_secretariat_storage',
            file_get_contents($manifests[0]) ?: ''
        );
        $this->assertStringNotContainsString(',mismatch', file_get_contents($verifications[0]) ?: '');
        $this->assertGreaterThan(0, filesize($storageArchives[0]));
    }
}
