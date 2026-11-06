<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\Referral\AffiliateCommissionService;
use Illuminate\Console\Command;

class ReleaseAffiliateCommissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'affiliates:release-commissions';

    /**
     * The console command description.
     */
    protected $description = 'Sweep and release affiliate commissions whose holding period has passed into their virtual wallets';

    /**
     * Execute the console command.
     */
    public function handle(AffiliateCommissionService $commissionService): int
    {
        $this->info('Checking for eligible pending affiliate commissions...');

        $releasedCount = $commissionService->releaseEligibleCommissions();

        if ($releasedCount > 0) {
            ActivityLogger::system("Affiliate sweeper released {$releasedCount} mature commission(s) into wallets", [
                'released_count' => $releasedCount,
            ]);
        }

        $this->info("Successfully credited {$releasedCount} eligible affiliate commission(s) to wallets.");

        return Command::SUCCESS;
    }
}

