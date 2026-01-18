<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use App\Consts;
use App\Models\OrderTransaction;
use App\Models\Process;
use App\Utils\BigNumber;
use App\Http\Services\PriceService;

class SpotFixAmalFee extends BaseLogCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spot:fix_amal_fee {start_date} {end_date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update profit old record';

    protected int $processedId = 0;

    protected int $batchSize = Consts::MARGIN_DEFAULT_BATCH_SIZE;
    private PriceService $priceService;

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
     * @return mixed
     */
    public function handle()
    {
        logger('Start Updating amal fee');
        $process = Process::firstOrCreate(['key' => $this->getProcessKey()]);
        $this->setProcessedId($process->processed_id);
        $startDate = $this->argument('start_date');
        $endDate = $this->argument('end_date');

        do {
            $trades = OrderTransaction::where('id', '>', $this->getProcessedId())
                ->whereBetween('executed_date', [$startDate, $endDate])
                ->where('currency', Consts::CURRENCY_USDT)
                ->where('sell_fee_amal', '<>', 0)
                ->orderBy('id', 'asc')
                ->limit($this->batchSize)
                ->get();

            DB::transaction(function () use ($trades, $process) {
                $process = Process::lockForUpdate()->find($process->id);
                foreach ($trades as $trade) {
                    $amalPrice = $this->getAmalPriceAt($trade->created_at);
                    $amalFee = BigNumber::new('1')->div($amalPrice)->mul($trade->sell_fee)->mul('0.5')->toString();

                    $refundAmount = BigNumber::new($trade->sell_fee_amal)->sub($amalFee)->toString();
                    if (BigNumber::new($refundAmount)->comp('0') > 0) {
                        logger("update fee: {$trade->id},{$trade->seller_email},{$trade->sell_fee_amal},$amalFee,$refundAmount,$amalPrice");
                        $trade->sell_fee_amal = $amalFee;
                        $trade->save();
                        $this->refundAmal($trade->seller_id, $refundAmount);
                    }
                    $this->setProcessedId($trade->id);
                    $process->processed_id = $trade->id;
                }
                $process->save();
            });
        } while (count($trades) === $this->batchSize);
        logger('End Updating amal fee');
    }

    private function getAmalPriceAt($time)
    {
        $price = $this->priceService->getPriceAt(Consts::CURRENCY_USDT, Consts::CURRENCY_AMAL, $time);
        logger($time - $price->created_at);
        return $price->price;
    }

    private function refundAmal($userId, $amount)
    {
        DB::table('spot_amal_accounts')
            ->where('id', $userId)
            ->update([
                'balance' => DB::raw("balance + $amount"),
                'available_balance' => DB::raw("available_balance + $amount"),
            ]);
    }

    private function getProcessedId(): int
    {
        return $this->processedId;
    }

    private function setProcessedId(int $id)
    {
        $this->processedId = $id;
    }

    protected function getProcessKey(): string
    {
        return 'fix_amal_fee_20200320';
    }
}
