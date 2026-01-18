<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\MasterdataService;
use App\Jobs\CreateBlockchainAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Services\Blockchain\SotatekBlockchainService;

class CreateBlockchainAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain_address:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create blockchain address';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /*$coins = MasterdataService::getCoins();
        if (!$coins)
            return;*/
        // get coins, network
        $networkCoins = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'n.enable' => true,
                'n.network_deposit_enable' => true,
                'nc.network_enable' => true,
                'nc.network_deposit_enable' => true,
            ])
            //->whereIn('c.coin', $coins)
            ->select(['c.coin', 'nc.network_id'])
            ->get();

        foreach ($networkCoins as $networkCoin) {
            try {
                $this->createAddressIfNeed($networkCoin);
            } catch (\Exception $exception) {
                //dd($exception);
                logger($exception);
                echo $networkCoin->coin. " - networkId: ". $networkCoin->network_id . "\n";
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function createAddressIfNeed($networkCoin)
    {
        $blockchainService = new SotatekBlockchainService($networkCoin);
        if (!$blockchainService->isSupportedCoin()) {
            logger()->error("Create Address Command: The coin '{$networkCoin->coin}' (networkId: {$networkCoin->network_id}) is unsported");
            return;
        }


        $setting = MasterdataService::getOneTable('settings')
            ->filter(function ($value) {
                return $value->key === Consts::SETTING_MIN_BLOCKCHAIN_ADDRESS_COUNT;
            })->first();

        $minCount = $setting ? $setting->value : 50;

        $count = DB::table('blockchain_addresses')
            ->where('network_id', $networkCoin->network_id)
            ->where('currency', $networkCoin->coin)
            ->count();

        // No need to dispatch a job
        $count = $minCount - $count + 1;

        if ($count > 0) {
            $createBlockchainAddress = new CreateBlockchainAddress($networkCoin, $count);
            $createBlockchainAddress->handle();
        }
    }
}
