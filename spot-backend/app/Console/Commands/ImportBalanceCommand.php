<?php

namespace App\Console\Commands;

use App\Http\Services\ImportDataService;
use App\Jobs\ImportBalanceForUserJob;
use App\Utils\BigNumber;
use Illuminate\Console\Command;

class ImportBalanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import balance from old system to current system';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $importDataService = (new ImportDataService());

        $balances = $importDataService->getAmalBalance();

        $totalBalance = 0;
        foreach ($balances as $balance) {
            $totalBalance = BigNumber::new($totalBalance)->add($balance->amount ?? 0)->toString();
        }

        // Create import user bot
        if (!$importDataService->getImportUser()) {
            $importDataService->createImportUser($totalBalance);
        }

        // Mapping user old system to current system
        $balances = $importDataService->getAmalBalance();
        $userIdsInOldSys = $importDataService->getUserIdOldSysFromBalance($balances);
        $userInCurrentSys = $importDataService->getUserIdNewSys($userIdsInOldSys);

        // Create deposit address for users
        $importDataService->createDepositAddressUsers($userInCurrentSys);

        // Dispatch Job Import balance
        foreach ($balances as $balance) {
            $currentUser = $userInCurrentSys[$balance->user_id];
            dispatch(new ImportBalanceForUserJob($currentUser, $balance));
        }

        dump('Dispatch Job Success. End.');
    }
}
