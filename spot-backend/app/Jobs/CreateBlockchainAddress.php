<?php

namespace App\Jobs;

use App\Consts;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Models\WalletHotWallet;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class CreateBlockchainAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $currency;
    protected SotatekBlockchainService $blockchainService;
    private int $count;

    /**
     * Create a new job instance.
     *
     * @param string $currency
     * @param int $count
     */
    public function __construct($networkCoin, int $count)
    {
        $this->count = $count;
        $this->currency = $networkCoin->coin;
        $this->blockchainService = new SotatekBlockchainService($networkCoin, $count);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $currency = $this->currency;

        switch ($currency) {
            case Consts::CURRENCY_XRP:
            case Consts::CURRENCY_EOS:
            case Consts::CURRENCY_TRX:
                $this->createAddress($this->currency);
                //$this->createUniqueAddress($this->currency);
                break;
            default:
                $this->createAddress($this->currency);
                break;
        }
    }

    private function createAddress($currency): bool
    {
        $addresses = $this->blockchainService->createAddress();

        $data = array_map(function ($address) use ($currency) {
            return [
                'currency' => $currency,
                'network_id' => $this->blockchainService->getNetworkId(),
                'blockchain_address' => $address,
                'blockchain_sub_address' => '',
                'address_id' => '',
                'path' => '',
                'device_id' => '',
                'available' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }, $addresses);

        return DB::table('blockchain_addresses')->insert($data);
    }

    private function createUniqueAddress($currency)
    {
        $address = $this->blockchainService->createAddress();
        $data = [];

        for ($i = 0; $i < $this->count; $i++) {
            $data[] = [
                'currency' => $currency,
                'blockchain_address' => $address,
                'blockchain_sub_address' => mt_rand(10000000, 99999999),
                'address_id' => '',
                'path' => '',
                'device_id' => '',
                'available' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        DB::table('blockchain_addresses')->insert($data);
    }
}
