<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\NotificationSetting;
use App\Models\UserPointTransaction;
use App\Models\UserPointConversion;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\Api\NajmBaharActivationApplicationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = dirname(__DIR__, 2).'/storage/framework/activation-concurrency';
$mode = $argv[1] ?? '';
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$write = static function (string $name, array $value) use ($dir): void {
    $tmp = $dir.'/'.$name.'.'.getmypid().'.tmp';
    file_put_contents($tmp, json_encode($value, JSON_THROW_ON_ERROR), LOCK_EX);
    rename($tmp, $dir.'/'.$name);
};

if ($mode === 'setup') {
    foreach (glob($dir.'/*') ?: [] as $path) {
        unlink($path);
    }
    $user = User::factory()->create(['is_system' => false, 'status' => 'active']);
    // Isolate point conversion from lazy notification-settings creation races.
    NotificationSetting::firstOrCreate(['user_id' => $user->id], NotificationSetting::getDefaults());
    $account = app(AccountService::class)->createMainAccountForUser((int) $user->id, 'Activation race user');
    $account->balance_active = 5;
    $account->balance_faded = 10;
    $account->committed_dim = 0;
    $account->balance = 15;
    $account->save();
    MonetaryPolicyVersion::create([
        'version' => 1,
        'status' => 'active',
        'parameters' => ['reputation_conversion_enabled' => true, 'reputation_to_gol_ratio' => 100],
        'reason' => 'isolated activation race probe',
        'effective_from' => now()->subMinute(),
        'approved_at' => now(),
    ]);
    UserPointTransaction::create([
        'user_id' => $user->id,
        'delta' => 300,
        'balance_after' => 300,
        'action' => 'activation_race_probe',
        'dimension' => 'participation',
        'convertible' => true,
        'source' => 'activation_race_probe',
    ]);
    $terms = app(NajmBaharActivationApplicationService::class)->eligibility($user);
    $write('fixture.json', ['user_id' => $user->id, 'account_id' => $account->id, 'terms' => $terms]);
    echo "activation concurrency fixture prepared\n";
    exit(0);
}

$fixture = json_decode(file_get_contents($dir.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
if ($mode === 'release') {
    $write('go', ['released' => true]);
    exit(0);
}

if ($mode === 'worker') {
    $index = (int) ($argv[2] ?? 0);
    if (! in_array($index, [1, 2], true)) {
        exit(2);
    }
    $write("ready-{$index}", ['pid' => getmypid()]);
    $limit = microtime(true) + 20;
    while (! is_file($dir.'/go')) {
        if (microtime(true) > $limit) {
            exit(3);
        }
        usleep(10000);
    }
    $user = User::query()->findOrFail((int) $fixture['user_id']);
    $terms = $fixture['terms'];
    $expected = array_intersect_key($terms, array_flip([
        'activation_contract_version', 'policy_version_id', 'policy_version',
        'conversion_ratio_points_per_gol', 'remaining_convertible_points',
        'dim_available_gol', 'max_activation_gol',
    ]));
    try {
        $result = app(NajmBaharActivationApplicationService::class)->activate(
            $user, 200, "native-parallel-key-{$index}", $expected,
        );
        $write("result-{$index}.json", ['status' => 'success', 'gol' => $result['activated_gol']]);
    } catch (Throwable $exception) {
        $write("result-{$index}.json", [
            'status' => 'error',
            'type' => get_class($exception),
            'message' => mb_substr($exception->getMessage(), 0, 500),
            'code' => method_exists($exception, 'getErrorCode') ? $exception->getErrorCode() : (string) $exception->getCode(),
        ]);
    }
    exit(0);
}

if ($mode === 'verify') {
    $results = [];
    foreach ([1, 2] as $index) {
        $path = $dir."/result-{$index}.json";
        if (! is_file($path)) {
            throw new RuntimeException("Missing worker result {$index}");
        }
        $results[] = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
    $successes = array_values(array_filter($results, fn ($r) => $r['status'] === 'success'));
    $account = Account::query()->findOrFail((int) $fixture['account_id']);
    $conversions = UserPointConversion::query()->where('user_id', $fixture['user_id'])->get();
    $consumed = (int) DB::table('user_point_consumptions')->where('user_id', $fixture['user_id'])->sum('points_consumed');
    echo json_encode(['workers' => $results, 'conversions' => $conversions->count(), 'consumed_points' => $consumed, 'active' => (int) $account->balance_active, 'dim' => (int) $account->balance_faded], JSON_THROW_ON_ERROR).PHP_EOL;
    $errors = array_values(array_filter($results, fn ($r) => $r['status'] === 'error'));
    $controlledErrors = count($errors) === 1 &&
        $errors[0]['type'] === App\Modules\NajmBahar\Services\Api\NajmBaharActivationException::class;
    if (! $controlledErrors || count($successes) !== 1 || (int) $successes[0]['gol'] !== 2 ||
        $conversions->where('status', 'applied')->count() !== 1 ||
        $consumed !== 200 || (int) $account->balance_active !== 7 ||
        (int) $account->balance_faded !== 8 || (int) $account->balance !== 15) {
        fwrite(STDERR, 'Parallel activation invariant violated'.PHP_EOL);
        exit(41);
    }
    exit(0);
}

throw new RuntimeException('Unsupported activation probe mode');
