<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class NajmBaharFinancialMutationBoundaryTest extends TestCase
{
    /**
     * Transitional allowlist. The goal is to shrink this list to the dedicated
     * monetary/transaction persistence boundary as Release A progresses.
     *
     * Admin UserController remains temporarily listed only because its legacy
     * purge-on-removal flow predates MembershipRetirementService. It is the
     * next explicit migration target; adding any new controller here is not
     * acceptable without a documented migration plan.
     */
    private const ALLOWED_MUTATION_FILES = [
        'app/Modules/NajmBahar/Services/MonetaryService.php',
        'app/Modules/NajmBahar/Services/TransactionService.php',
        'app/Modules/NajmBahar/Services/InternalAccountTransferService.php',
        'app/Modules/NajmBahar/Services/SubAccountService.php',
        'app/Modules/NajmBahar/Services/AccountService.php',
        // Dedicated Release B/C financial persistence boundaries.
        'app/Modules/NajmBahar/Services/PublicExecutionPaymentService.php',
        'app/Modules/NajmBahar/Services/CrossOwnerActiveSubAccountTransferService.php',
        'app/Modules/NajmBahar/Services/MainAccountSystemTransferService.php',
        'app/Modules/NajmBahar/Services/AccountInvariantService.php',
        'app/Modules/NajmBahar/Services/ScheduledSubAccountTransferExecutor.php',
        'app/Modules/NajmBahar/Services/PublicExecutionReversalService.php',
        'app/Modules/NajmBahar/Services/DimCommitmentService.php',
        'app/Modules/NajmBahar/Services/InternalSubAccountTransferService.php',
        'app/Modules/NajmBahar/Services/SubAccountSystemTransferService.php',
        // Dedicated atomic Active Bahar reservation settlement/refund boundary.
        'app/Modules/NajmBahar/Services/ActiveBaharReservationService.php',
        'app/Console/Commands/FixCorruptedBalances.php',
        'app/Http/Controllers/Admin/UserController.php',
    ];

    public function test_direct_balance_mutations_are_confined_to_known_financial_boundaries(): void
    {
        $root = dirname(__DIR__, 2);
        $targets = [
            $root . '/app/Modules/NajmBahar',
            $root . '/app/Http/Controllers',
            $root . '/app/Console/Commands',
        ];

        $violations = [];
        foreach ($targets as $target) {
            if (! is_dir($target)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
            $phpFiles = new RegexIterator($iterator, '/\.php$/i');

            foreach ($phpFiles as $file) {
                $path = str_replace('\\', '/', $file->getPathname());
                $relative = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');

                $contents = file_get_contents($file->getPathname());
                if ($contents === false) {
                    continue;
                }

                // Match actual writes, not comparisons such as `balance_active === 0`.
                if (! preg_match('/->balance(?:_active|_faded)?\s*=(?!=)|->(?:increment|decrement)\(\s*[\'\"]balance(?:_active|_faded)?[\'\"]/', $contents)) {
                    continue;
                }

                if (in_array($relative, self::ALLOWED_MUTATION_FILES, true)) {
                    continue;
                }

                if (str_starts_with($relative, 'app/Http/Controllers/')
                    && ! str_contains($contents, 'NajmBahar')
                    && ! str_contains($contents, 'balance_faded')
                    && ! str_contains($contents, 'balance_active')) {
                    continue;
                }

                $violations[] = $relative;
            }
        }

        $this->assertSame([], array_values(array_unique($violations)),
            "Direct financial balance mutation detected outside the transitional financial boundary:\n"
            . implode("\n", array_unique($violations))
        );
    }
}
