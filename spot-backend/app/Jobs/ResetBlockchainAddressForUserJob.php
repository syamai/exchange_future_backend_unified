<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ResetBlockchainAddressForUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ids;
    protected $table;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ids, $table)
    {
        $this->ids = $ids;
        $this->table = $table;
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
            $dataTable = DB::table($this->table)->whereIn('id', $this->ids)->update(['blockchain_address' => null]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error("reset blockchain address of list ids: $this->ids in table: $this->table error:" . $e->getMessage());
            throw $e;
        }
    }
}
