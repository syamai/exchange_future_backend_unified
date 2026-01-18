<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Voucher;
use App\Models\User;
use App\Consts;
use Illuminate\Support\Facades\DB;

class CreateUserVolumeByVoucher implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $voucher_id;
    private $user_id;

     /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($voucher_id, $user_id)
    {
        $this->voucher_id = $voucher_id;
        $this->user_id = $user_id;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        DB::beginTransaction();
        try {
            $voucher_id = $this->voucher_id;
            $user_id = $this->user_id;
            $voucher = Voucher::find($voucher_id);

            if ($voucher && !$voucher->deleted_at) {
                $this->createUserVolume($voucher_id, $user_id);
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger()->error("Create user volume $this->voucher_id error:" . $e->getMessage());
            throw $e;
        }
    }

    private function createUserVolume($voucher_id, $user_id)
    {
        $table = "user_trade_volume_per_days";
        if ($this->checkHasTable($table)) {
            DB::table($table)->insert([
                'user_id' => $id,
                'voucher_id' => $voucherId,
                'type' => Consts::TYPE_EXCHANGE_BALANCE,
                'volume' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }

    private function checkHasTable($tableName)
    {
        return $hasTable = Schema::hasTable($tableName);
    }
}
