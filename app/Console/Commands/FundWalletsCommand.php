<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Stock\Services\WalletService;
use Illuminate\Console\Command;

class FundWalletsCommand extends Command
{
    protected $signature = 'stock:fund-wallets {amount=100000000 : Amount in rials to credit each wallet} {--description=Administrative funding}';

    protected $description = 'Credit all user stock wallets with a specified amount (default 100,000,000 rials).';

    public function handle(WalletService $walletService): int
    {
        $amount = (int) $this->argument('amount');

        if ($amount <= 0) {
            $this->error('Amount must be a positive integer.');
            return self::FAILURE;
        }

        $description = (string) $this->option('description') ?: 'Administrative funding';
        $totalFunded = 0;
        $usersProcessed = 0;

        User::chunkById(100, function ($users) use (&$usersProcessed, &$totalFunded, $walletService, $amount, $description) {
            foreach ($users as $user) {
                $wallet = $walletService->getOrCreateWallet($user->id);
                $walletService->credit($wallet, $amount, $description);

                $usersProcessed++;
                $totalFunded += $amount;
            }
        });

        $this->info("Funded {$usersProcessed} wallet(s) with {$amount} rials each.");
        $this->info('Total credited: ' . number_format($totalFunded) . ' rials.');

        return self::SUCCESS;
    }
}

