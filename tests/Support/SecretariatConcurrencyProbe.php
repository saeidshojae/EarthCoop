<?php

declare(strict_types=1);

use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Modules\Secretariat\Models\SecretariatSequence;
use App\Modules\Secretariat\Services\RegistryNumberService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? null;
$stateDir = dirname(__DIR__, 2) . '/storage/framework/secretariat-concurrency';
$officeFile = $stateDir . '/office-id';
$barrierFile = $stateDir . '/go';

if (! is_dir($stateDir) && ! mkdir($stateDir, 0775, true) && ! is_dir($stateDir)) {
    fwrite(STDERR, "Unable to create concurrency state directory.\n");
    exit(2);
}

if ($mode === 'setup') {
    foreach (glob($stateDir . '/*') ?: [] as $file) {
        @unlink($file);
    }

    $office = app(SecretariatOfficeService::class)->create([
        'code' => 'S1-CONCURRENCY-' . bin2hex(random_bytes(4)),
        'name' => 'S1 Registry Concurrency Probe',
        'office_type' => 'central',
    ]);

    file_put_contents($officeFile, (string) $office->id, LOCK_EX);
    echo $office->id . PHP_EOL;
    exit(0);
}

if ($mode === 'release') {
    file_put_contents($barrierFile, 'go', LOCK_EX);
    exit(0);
}

if ($mode === 'worker') {
    $worker = isset($argv[2]) ? (int) $argv[2] : 0;
    if ($worker < 1 || ! is_file($officeFile)) {
        fwrite(STDERR, "Worker requires a valid worker number and setup state.\n");
        exit(2);
    }

    $deadline = microtime(true) + 15;
    while (! is_file($barrierFile)) {
        if (microtime(true) >= $deadline) {
            fwrite(STDERR, "Worker {$worker} timed out waiting for barrier.\n");
            exit(3);
        }
        usleep(10_000);
    }

    $officeId = (int) trim((string) file_get_contents($officeFile));
    $office = SecretariatOffice::query()->findOrFail($officeId);

    $allocation = DB::transaction(
        fn () => app(RegistryNumberService::class)->allocate($office, 'official_report', 2099),
        5
    );

    file_put_contents(
        $stateDir . '/worker-' . $worker . '.json',
        json_encode($allocation, JSON_THROW_ON_ERROR),
        LOCK_EX
    );

    echo $allocation['number'] . PHP_EOL;
    exit(0);
}

if ($mode === 'verify') {
    if (! is_file($officeFile)) {
        fwrite(STDERR, "Concurrency setup state is missing.\n");
        exit(2);
    }

    $officeId = (int) trim((string) file_get_contents($officeFile));
    $files = glob($stateDir . '/worker-*.json') ?: [];
    $expectedWorkers = isset($argv[2]) ? (int) $argv[2] : 0;

    if (count($files) !== $expectedWorkers) {
        fwrite(STDERR, sprintf("Expected %d worker results, found %d.\n", $expectedWorkers, count($files)));
        exit(4);
    }

    $sequences = [];
    $numbers = [];
    foreach ($files as $file) {
        $allocation = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $sequences[] = (int) $allocation['sequence'];
        $numbers[] = (string) $allocation['number'];
    }

    sort($sequences, SORT_NUMERIC);
    $expected = range(1, $expectedWorkers);

    if ($sequences !== $expected) {
        fwrite(STDERR, 'Allocated sequences are not gap-free and unique: ' . json_encode($sequences) . PHP_EOL);
        exit(5);
    }

    if (count(array_unique($numbers)) !== $expectedWorkers) {
        fwrite(STDERR, "Duplicate registry numbers were allocated.\n");
        exit(6);
    }

    $lastValue = (int) SecretariatSequence::query()
        ->where('office_id', $officeId)
        ->where('calendar_year', 2099)
        ->where('record_family', 'REP')
        ->value('last_value');

    if ($lastValue !== $expectedWorkers) {
        fwrite(STDERR, "Sequence row last_value mismatch: {$lastValue}.\n");
        exit(7);
    }

    echo sprintf(
        "Concurrency probe passed: %d parallel allocations, unique sequences 1..%d, last_value=%d.\n",
        $expectedWorkers,
        $expectedWorkers,
        $lastValue
    );
    exit(0);
}

fwrite(STDERR, "Usage: php tests/Support/SecretariatConcurrencyProbe.php setup|release|worker <n>|verify <count>\n");
exit(1);
