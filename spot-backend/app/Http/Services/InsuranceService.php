<?php
namespace App\Http\Services;

use App\Consts;
use App\Models\User;
use App\Utils\BigNumber;

use Illuminate\Support\Facades\DB;

class InsuranceService
{
    public function getInsuranceFund()
    {
        return [
            'insuranceFund' => $this->getBtcBalance(Consts::HACKING_INSURANCE_FUND_EMAIL),
            'buyBackFund' => $this->getBtcBalance(Consts::BUY_BACK_FUND_EMAIL),
        ];
    }

    private function getBtcBalance($email)
    {
        $user = User::where('email', $email)->first();
        if (!$user) {
            return '0';
        }

        $btcAccount = DB::table('btc_accounts')->find($user->id);
        if (!$btcAccount) {
            return '0';
        }

        return $btcAccount->balance;
    }
}
