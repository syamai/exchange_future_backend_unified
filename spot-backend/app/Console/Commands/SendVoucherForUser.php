<?php

namespace App\Console\Commands;

use App\Http\Services\VoucherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SendVoucherForUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vouchers:send_vouchers_users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send voucher for user';
    protected $type;

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
        try {
            $this->voucherService->sendVoucherForUser();
            return true;
        } catch (HttpException $e) {
            Log::error($e);
            return false;
        }
    }
}
