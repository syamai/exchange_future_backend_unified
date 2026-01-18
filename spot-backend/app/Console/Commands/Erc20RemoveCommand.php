<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\RegisterErc20Service;
use App\Models\Coin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SotaWallet\SotaWalletService;

class Erc20RemoveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erc20:remove {currency?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $currency = $this->argument('currency');

        $msg = $currency ?? 'all';

        if (!$this->confirm("Do you want to remove {$msg} token erc20 ?")) {
            $this->info('Cancel to remove Erc20 command');

            return;
        }

        if ($currency === null) {
            $currencies = $this->getAllCurrencies();
        } else {
            if ($this->validCurrency($currency) === 0) {
                $this->info('Currency is not token erc20 or blacklist not remove');
                return;
            }
            $currencies = [$currency];
        }

        $bar = $this->output->createProgressBar(count($currencies));

        $bar->start();

        try {
            foreach ($currencies as $currency) {
                $this->info(" =========> $currency");
                $this->remove($currency);
                $bar->advance();
            }

            app(RegisterErc20Service::class)->cacheClear();

            $bar->finish();
            $this->info(" Success");
        } catch (\Exception $exception) {
            logger($exception);
            $this->info($exception->getMessage());
        }
    }

    protected function scanFile($fileName)
    {
        foreach (glob('database/migrations/erc20/*') as $dir) {
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') as $file) {
                    $this->removeFile($file, $fileName);
                }
            } else {
                $this->removeFile($dir, $fileName);
            }
        }
    }

    protected function remove($currency)
    {
        $contractAddress = DB::table('coins')->where('coin', $currency)->first()->contract_address;

        DB::table('coins')->where('coin', $currency)->delete();
        DB::table('coins_confirmation')->where('coin', $currency)->delete();
        DB::table('coin_settings')->where('coin', $currency)->delete();
        DB::table('price_groups')->where('coin', $currency)->delete();
        DB::table('withdrawal_limits')->where('currency', $currency)->delete();
        DB::table('market_fee_setting')->where('coin', $currency)->delete();

        Schema::dropIfExists($currency . "_accounts");
        Schema::dropIfExists("spot_" . $currency . "_accounts");

        DB::table('migrations')->where('migration', 'LIKE', "%{$currency}_accounts%")->delete();

        $this->scanFile("_{$currency}_accounts_");

        SotaWalletService::deleteErc20($contractAddress);
    }

    protected function removeFile($file, $fileName)
    {
        $isFound = strpos($file . '', $fileName) !== false;

        if ($isFound) {
            unlink($file . '');
        }
    }

    protected function queryCurrency()
    {
        $coinBlackList = [
            Consts::CURRENCY_AMAL,
            Consts::CURRENCY_USDT
        ];

        return Coin::network()
            ->whereNotIn('coin', $coinBlackList)
            ->where('type', Consts::ETH_TOKEN);
    }


    protected function getAllCurrencies()
    {
        return $this->queryCurrency()->pluck('coin')->toArray();
    }

    public function validCurrency($currency)
    {
        return $this->queryCurrency()
            ->where('coin', $currency)
            ->count();
    }
}
