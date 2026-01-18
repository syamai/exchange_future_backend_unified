<?php
namespace Snapshot\Console\Commands;

use App\Consts;
use App\Http\Services\BalanceService;
use App\Http\Services\Blockchain\CoinConfigs;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\HotWalletService;
use App\Models\AmalSetting;
use App\Models\Coin;
use App\Models\CoinsConfirmation;
use App\Utils\BigNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Snapshot\Http\Services\TakeProfitService;
use Snapshot\Models\TakeProfit;

class SnapshotTakeProfitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snapshot:take-profit';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    protected $hotWalletService, $takeProfitService, $balanceService;
    public function __construct()
    {
        parent::__construct();
        $this->hotWalletService = new HotWalletService;
        $this->takeProfitService = new TakeProfitService(new TakeProfit());
        $this->balanceService = app(BalanceService::class);
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->snapshotNormal();
        $this->snapshotUsd();
        //$this->snapshotAMAL();
    }
    protected function snapshotNormal()
    {
        $balanceWallets = $this->hotWalletService->statisticBalance();
        $coins = Coin::network()->whereNotIn('coin', [Consts::CURRENCY_USD, Consts::CURRENCY_AMAL])->pluck('coin');
        $networkCoins = DB::table('coins', 'c')
            ->join('network_coins as nc', 'c.id', 'nc.coin_id')
            ->join('networks as n', 'nc.network_id', 'n.id')
            ->where([
                'n.enable' => true,
                'nc.network_enable' => true,
            ])
            ->whereIn('c.coin', $coins)
            ->select(['c.coin', 'nc.network_id'])
            ->get();
        $balanceWalletCoins = [];
        foreach ($networkCoins as $networkCoin) {
            try {
                //$this->snapshotCurrency($networkCoin->coin, $networkCoin->network_id, $balanceWallets);
                $currency = CoinConfigs::getCoinConfig($networkCoin->coin, $networkCoin->network_id)->getCurrentTx();
                if (isset($balanceWallets[$currency])) {
                    $blockchainService = new SotatekBlockchainService($currency);
                    $totalWalletBalance = $blockchainService->fixTransactionAmount($balanceWallets[$currency]['totalBalance'], true);

                    if (!isset($balanceWalletCoins[$networkCoin->coin])) {
                        $balanceWalletCoins[$networkCoin->coin] = [
                            'balance' => 0
                        ];
                    }
                    $balanceWalletCoins[$networkCoin->coin]['balance'] = BigNumber::new($totalWalletBalance)->add($balanceWalletCoins[$networkCoin->coin]['balance'])->toString();

                }
            } catch (\Exception $exception) {
                Log::error($exception);
            }
        }
        try {
            foreach ($balanceWalletCoins as $coin => $i) {
                $totalBalance = $this->balanceService->statisticBalance($coin);
                $amount = BigNumber::new($i['balance'])->sub($totalBalance)->toString();
                $this->snapshot($coin, $amount);
            }
        } catch (\Exception $exception) {
            Log::error($exception);
        }
    }
    protected function snapshotCurrency($coin, $networkId, $balanceWallets)
    {
        $currency = CoinConfigs::getCoinConfig($coin, $networkId)->getCurrentTx();
        if (isset($balanceWallets[$currency])) {
            $totalBalance = $this->balanceService->statisticBalance($coin);
            $amount = $this->mathAmount($currency, $balanceWallets[$currency]['totalBalance'], $totalBalance);
            $this->snapshot($coin, $amount);
        }
    }
    protected function snapshotUsd()
    {
        $this->snapshot(Consts::CURRENCY_USD, $this->balanceService->statisticBalance(Consts::CURRENCY_USD));
    }
    protected function snapshotAMAL()
    {
        $currency = Consts::CURRENCY_AMAL;
        $total = $this->balanceService->statisticBalance($currency);
        $walletTotal = AmalSetting::value('total');
        $amount = $this->mathAmount($currency, $walletTotal, $total);
        $this->snapshot($currency, $amount);
    }
    protected function mathAmount($currency, $balanceWallet, $totalBalance)
    {
        $blockchainService = new SotatekBlockchainService($currency);
        $totalWalletBalance = $blockchainService->fixTransactionAmount($balanceWallet, true);
        return BigNumber::new($totalWalletBalance)->sub($totalBalance)->toString();
    }
    protected function snapshot($currency, $amount)
    {
        $data = [
            'currency' => $currency,
            'amount' => $amount,
        ];
        $this->takeProfitService->store($data);
    }
}
