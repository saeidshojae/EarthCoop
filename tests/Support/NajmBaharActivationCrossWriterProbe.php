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
use App\Services\ReputationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$dir = dirname(__DIR__, 2).'/storage/framework/activation-crosswriter';
if (! is_dir($dir)) mkdir($dir, 0775, true);
$mode = $argv[1] ?? '';
$write = static function (string $name, array $data) use ($dir): void {
    $tmp = $dir.'/'.$name.'.'.getmypid().'.tmp';
    file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
    rename($tmp, $dir.'/'.$name);
};

if ($mode === 'setup') {
    foreach (glob($dir.'/*') ?: [] as $file) unlink($file);
    $user = User::factory()->create(['is_system' => false, 'status' => 'active']);
    NotificationSetting::firstOrCreate(['user_id' => $user->id], NotificationSetting::getDefaults());
    $account = app(AccountService::class)->createMainAccountForUser($user->id, 'Cross-writer race test');
    $account->update(['balance_active' => 5, 'balance_faded' => 10, 'committed_dim' => 0, 'balance' => 15]);
    MonetaryPolicyVersion::create([
        'version' => 1, 'status' => 'active',
        'parameters' => ['reputation_conversion_enabled' => true, 'reputation_to_gol_ratio' => 100],
        'reason' => 'cross-writer race probe', 'effective_from' => now()->subMinute(), 'approved_at' => now(),
    ]);
    // Existing ordinary writer creates the initial convertible award.
    app(ReputationService::class)->addPoints($user, 300, 'probe_award', [], null, 'race_probe', 'participation', true, 'cross-writer-seed-0001');
    $terms = app(NajmBaharActivationApplicationService::class)->eligibility($user);
    $write('fixture.json', ['user_id' => $user->id, 'account_id' => $account->id, 'terms' => $terms]);
    echo "cross-writer fixture prepared\n";
    exit(0);
}
if ($mode === 'release') {
    $write('go', ['released' => true]);
    exit(0);
}
$fixture = json_decode(file_get_contents($dir.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
if ($mode === 'worker') {
    $task = $argv[2] ?? '';
    if (! in_array($task, ['activate', 'reverse'], true)) exit(2);
    $write("ready-{$task}", ['pid' => getmypid()]);
    $deadline = microtime(true) + 20;
    while (! is_file($dir.'/go')) {
        if (microtime(true) > $deadline) exit(3);
        usleep(10000);
    }
    $user = User::findOrFail($fixture['user_id']);
    try {
        if ($task === 'reverse') {
            app(ReputationService::class)->addPoints(
                $user, -200, 'probe_reversal', [], null, 'race_probe',
                'participation', true, 'cross-writer-reverse-0001'
            );
            $write('result-reverse.json', ['status' => 'success']);
        } else {
            $expected = array_intersect_key($fixture['terms'], array_flip([
                'activation_contract_version', 'policy_version_id', 'policy_version',
                'conversion_ratio_points_per_gol', 'remaining_convertible_points',
                'dim_available_gol', 'max_activation_gol',
            ]));
            $r = app(NajmBaharActivationApplicationService::class)->activate($user, 200, 'cross-writer-activation-0001', $expected);
            $write('result-activate.json', ['status' => 'success', 'gol' => $r['activated_gol']]);
        }
    } catch (Throwable $e) {
        $write("result-{$task}.json", ['status' => 'error', 'type' => get_class($e), 'message' => mb_substr($e->getMessage(), 0, 250)]);
    }
    exit(0);
}
if ($mode === 'verify') {
    $results = [];
    foreach (['activate', 'reverse'] as $task) {
        $file = $dir."/result-{$task}.json";
        if (! is_file($file)) { fwrite(STDERR, "missing {$task}\n"); exit(42); }
        $results[$task] = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }
    $account = Account::findOrFail($fixture['account_id']);
    $conversions = UserPointConversion::where('user_id', $fixture['user_id'])->where('status', 'applied')->get();
    $consumed = (int) DB::table('user_point_consumptions')->where('user_id', $fixture['user_id'])->sum('points_consumed');
    $reversed = abs((int) UserPointTransaction::where('user_id', $fixture['user_id'])->where('delta', '<', 0)->where('convertible', true)->sum('delta'));
    echo json_encode(['results' => $results, 'applied' => $conversions->count(), 'consumed' => $consumed, 'reversed' => $reversed, 'active' => $account->balance_active, 'dim' => $account->balance_faded], JSON_THROW_ON_ERROR).PHP_EOL;
    $valid = $results['reverse']['status'] === 'success' && $reversed === 200 &&
        $consumed + $reversed <= 300 &&
        (int) $account->balance_active + (int) $account->balance_faded === 15 &&
        (($results['activate']['status'] === 'success' && $consumed === 200 && $conversions->count() === 1) ||
        ($results['activate']['status'] === 'error' && $consumed === 0 && $conversions->count() === 0));
    if (! $valid) { fwrite(STDERR, "Cross-writer financial invariant violated\n"); exit(41); }
    exit(0);
}
fwrite(STDERR, "Unsupported probe mode\n");
exit(2);
