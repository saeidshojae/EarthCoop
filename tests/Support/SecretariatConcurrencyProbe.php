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
$root = dirname(__DIR__, 2);
$stateDir = $root . '/storage/framework/secretariat-concurrency';
$officeFile = $stateDir . '/office-id';
$barrierFile = $stateDir . '/go';

function failProbe(string $message, int $code): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function writeAtomically(string $path, string $payload): void
{
    $dir = dirname($path);
    $tmp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    $expected = strlen($payload);
    $written = file_put_contents($tmp, $payload, LOCK_EX);

    if ($written !== $expected) {
        @unlink($tmp);
        failProbe("Atomic write failed for {$path}: expected {$expected} bytes, wrote " . var_export($written, true), 8);
    }

    if (! rename($tmp, $path)) {
        @unlink($tmp);
        failProbe("Atomic publish failed for {$path}.", 9);
    }
}

if (! is_dir($stateDir) && ! mkdir($stateDir, 0775, true) && ! is_dir($stateDir)) {
    failProbe('Unable to create concurrency state directory.', 2);
}

if ($mode === 'setup') {
    foreach (glob($stateDir . '/*') ?: [] as $file) {
        if (is_file($file) && ! unlink($file)) {
            failProbe("Unable to clean stale concurrency file {$file}.", 10);
        }
    }

    $office = app(SecretariatOfficeService::class)->create([
        'code' => 'S1-CONCURRENCY-' . bin2hex(random_bytes(4)),
        'name' => 'S1 Registry Concurrency Probe',
        'office_type' => 'central',
    ]);

    writeAtomically($officeFile, (string) $office->id);
    echo $office->id . PHP_EOL;
    exit(0);
}

if ($mode === 'release') {
    writeAtomically($barrierFile, 'go');
    exit(0);
}

if ($mode === 'worker') {
    $worker = isset($argv[2]) ? (int) $argv[2] : 0;
    if ($worker < 1 || ! is_file($officeFile)) {
        failProbe('Worker requires a valid worker number and setup state.', 2);
    }

    $deadline = microtime(true) + 15;
    while (! is_file($barrierFile)) {
        if (microtime(true) >= $deadline) {
            failProbe("Worker {$worker} timed out waiting for barrier.", 3);
        }
        usleep(10_000);
    }

    $officeId = (int) trim((string) file_get_contents($officeFile));
    $office = SecretariatOffice::query()->findOrFail($officeId);

    $allocation = DB::transaction(
        fn () => app(RegistryNumberService::class)->allocate($office, 'official_report', 2099),
        5
    );

    $resultPath = $stateDir . '/worker-' . $worker . '.json';
    $payload = json_encode([
        ...$allocation,
        'worker' => $worker,
        'pid' => getmypid(),
    ], JSON_THROW_ON_ERROR);

    writeAtomically($resultPath, $payload);

    if (! is_file($resultPath) || filesize($resultPath) !== strlen($payload)) {
        failProbe("Worker {$worker} result was not durably published.", 11);
    }

    echo "worker={$worker} number={$allocation['number']} sequence={$allocation['sequence']}" . PHP_EOL;
    exit(0);
}

if ($mode === 'verify') {
    if (! is_file($officeFile)) {
        failProbe('Concurrency setup state is missing.', 2);
    }

    $officeId = (int) trim((string) file_get_contents($officeFile));
    $expectedWorkers = isset($argv[2]) ? (int) $argv[2] : 0;
    if ($expectedWorkers < 1) {
        failProbe('Verify requires a positive worker count.', 2);
    }

    // All worker processes have already been waited on by the workflow. Polling
    // here is only a bounded durability/visibility guard for runner filesystems.
    $deadline = microtime(true) + 2.0;
    do {
        $missing = [];
        for ($worker = 1; $worker <= $expectedWorkers; $worker++) {
            if (! is_file($stateDir . '/worker-' . $worker . '.json')) {
                $missing[] = $worker;
            }
        }
        if ($missing === []) {
            break;
        }
        usleep(50_000);
    } while (microtime(true) < $deadline);

    if ($missing !== []) {
        $present = array_map('basename', glob($stateDir . '/worker-*.json') ?: []);
        failProbe(
            'Missing worker result files: ' . implode(',', $missing) . '; present=' . json_encode($present),
            4
        );
    }

    $sequences = [];
    $numbers = [];
    $seenWorkers = [];

    for ($worker = 1; $worker <= $expectedWorkers; $worker++) {
        $file = $stateDir . '/worker-' . $worker . '.json';
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            failProbe("Worker {$worker} result is unreadable or empty.", 12);
        }

        $allocation = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if ((int) ($allocation['worker'] ?? 0) !== $worker) {
            failProbe("Worker result identity mismatch for file {$file}.", 13);
        }

        $seenWorkers[] = $worker;
        $sequences[] = (int) $allocation['sequence'];
        $numbers[] = (string) $allocation['number'];
    }

    sort($sequences, SORT_NUMERIC);
    $expected = range(1, $expectedWorkers);

    if ($seenWorkers !== $expected) {
        failProbe('Worker identity set mismatch: ' . json_encode($seenWorkers), 14);
    }

    if ($sequences !== $expected) {
        failProbe('Allocated sequences are not gap-free and unique: ' . json_encode($sequences), 5);
    }

    if (count(array_unique($numbers)) !== $expectedWorkers) {
        failProbe('Duplicate registry numbers were allocated.', 6);
    }

    $lastValue = (int) SecretariatSequence::query()
        ->where('office_id', $officeId)
        ->where('calendar_year', 2099)
        ->where('record_family', 'REP')
        ->value('last_value');

    if ($lastValue !== $expectedWorkers) {
        failProbe("Sequence row last_value mismatch: {$lastValue}.", 7);
    }

    echo sprintf(
        "Concurrency probe passed: %d parallel allocations, unique sequences 1..%d, last_value=%d.\n",
        $expectedWorkers,
        $expectedWorkers,
        $lastValue
    );
    exit(0);
}

failProbe('Usage: php tests/Support/SecretariatConcurrencyProbe.php setup|release|worker <n>|verify <count>', 1);
