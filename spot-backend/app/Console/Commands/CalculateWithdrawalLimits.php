<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Services\MasterdataService;
use App\Http\Services\PriceService;
use App\Models\WithdrawalLimit;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Utils\BigNumber;

class CalculateWithdrawalLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:withdrawal_limits';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate withdrawal limits follow BTC';

    private $priceService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->priceService = new PriceService();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        DB::beginTransaction();
        $count = 0;
        while ($count < 3) {
            try {
                $data = MasterdataService::getOneTable('withdrawal_limits');

                for ($level = 1; $level <= 4; $level += 1) {
                    $withdrawalLimits = $data->filter(function ($item) use ($level) {
                        return $item->security_level === $level;
                    });
                    $this->updateWithdrawLimitFollowBtc($withdrawalLimits);
                }
                DB::commit();

                MasterdataService::clearCacheOneTable('withdrawal_limits');
                return 0;
            } catch (Exception $e) {
                DB::rollBack();
                logger()->error($e);
                // TODO: Send a email to admin
                // Mail::to
                $count += 1;
            }
        }
    }

    private function updateWithdrawLimitFollowBtc($data)
    {
        list($btc, $withdrawalLimits) = $data->partition(function ($item) {
            return $item->currency === 'btc';
        });

        $btc = $btc->first();

        $withdrawalLimits->filter(function ($item) {
                return $item->currency !== 'usd';
        })
            ->each(function ($withdrawalLimit) use ($btc) {
                $price = $this->priceService->getCurrentPrice($withdrawalLimit->currency, 'btc');

                $daylyLimit = BigNumber::new($btc->daily_limit)->mul($price->price)->toString();
                $limit = BigNumber::new($btc->limit)->mul($price->price)->toString();

                WithdrawalLimit::where('id', $withdrawalLimit->id)
                    ->update([
                        'limit' => $limit,
                        'daily_limit' => $daylyLimit,
                    ]);
            });
    }
}
