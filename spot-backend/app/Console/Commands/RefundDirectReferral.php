<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\ReferralService;
use App\Models\CompleteTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Log;
use DB;
use Exception;

class RefundDirectReferral extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:direct';

    private $referralService;
    protected int $batchSize = Consts::MARGIN_DEFAULT_BATCH_SIZE;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(ReferralService $referralService) {
        parent::__construct();
        $this->referralService = $referralService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logTime = Carbon::now()->format('Y-m-d H:i:s');
        $this->info("{$logTime} START-[{$this->signature}] {$this->description} =====================\n");

        $date = Carbon::now()->format('Y-m-d');
        $channel = Log::build([
            'driver' => 'single',
            'path' => storage_path("logs/schedule/{$this->signature}/{$date}.log"),
        ]);

        Log::stack([$channel])->info("BEGIN");

        $listSuccess = [];
        $listError = [];

        do {
            $completeTransactions = CompleteTransaction::where('is_calculated_direct_commission', 0)->limit($this->batchSize)->get();
            foreach ($completeTransactions as $transaction) {
                DB::beginTransaction();
                try {
                    $this->referralService->addDirectCommission($transaction);
                    
                    $transaction->is_calculated_direct_commission = 1;
                    $transaction->save();
                    $listSuccess[] = $transaction->id;
                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::stack([$channel])->error('RefundDirectReferral. Failed to calculate direct commission for transaction: ' . $transaction->id);
                    Log::stack([$channel])->error($e);
                    $transaction->is_calculated_direct_commission = 2;
                    $transaction->save();
                    $listError[] = $transaction->id;
                }
            }

        } while (count($completeTransactions) === $this->batchSize);

        Log::stack([$channel])->info('Success: ' . count($listSuccess)
             . "\n Error: " . count($listError)
             . "\n Error List: " . implode(',', $listError)
             . "\n END");
        
        $this->info("{$logTime} END-[{$this->signature}] {$this->description} ==========job completed===========\n");
    }
}
