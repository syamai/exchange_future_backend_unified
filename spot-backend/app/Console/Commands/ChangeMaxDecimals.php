<?php

namespace App\Console\Commands;

use App\Consts;
use App\Models\PriceGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ChangeMaxDecimals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orderbook:change_max_decimals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Order Book - Change Max Decimal: ';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Order Book - Change Max Decimal');
        PriceGroup::query()->update(['value' => 2]);

        $this->changeBTCDecimal();
        $this->changeETHDecimal();
        $this->changeUSDDecimal();
        $this->changeUSDTDecimal();

        PriceGroup::where('value', '>', 1)->delete();
        Artisan::call('cache:clear');
    }

    private function changeDecimal($currency, $maxDecimal)
    {
        $decimal = $maxDecimal == 8 ? 0.00000001 : 0.01;
        $groups = $maxDecimal == 8 ? [3, 2, 1, 0] : [2, 1, 0];
        foreach ($groups as $group) {
            $priceGroups = PriceGroup::where('currency', $currency)
                ->where('group', $group)
                ->get();

            foreach ($priceGroups as $priceGroup) {
                $priceGroup->value = $decimal;
                $priceGroup->save();
            }
            $this->info(json_encode($priceGroups));
            $this->info(count($priceGroups));

            $decimal *= 10;
        }
    }
    private function changeBTCDecimal()
    {
        $this->changeDecimal(Consts::CURRENCY_BTC, 8);
    }

    private function changeETHDecimal()
    {
        $this->changeDecimal(Consts::CURRENCY_ETH, 8);
    }

    private function changeUSDDecimal()
    {
        $this->changeDecimal(Consts::CURRENCY_USD, 2);
    }

    private function changeUSDTDecimal()
    {
        $this->changeDecimal(Consts::CURRENCY_USDT, 2);
    }
}
