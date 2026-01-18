<?php

namespace App\Console\Commands;

use App\Http\Services\VoucherService;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStatusVoucher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vouchers:update_expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command update status vouchers expired';

    protected $voucherService;
    public function __construct(VoucherService $voucherService)
    {
        parent::__construct();
        $this->voucherService = $voucherService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::beginTransaction();
        try {
//            $this->voucherService->updateVouchersExpired(Voucher::class);
            $this->voucherService->updateVouchersExpired(UserVoucher::class);
            DB::commit();
            return true;
        } catch (\HttpException $e) {
            DB::rollBack();
            Log::error($e);
            return false;
        }
    }
}
