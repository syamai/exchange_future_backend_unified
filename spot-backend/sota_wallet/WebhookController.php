<?php

namespace SotaWallet;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class WebhookController extends Controller
{
    public function onReceiveTransaction1(Request $request)
    {
        return SotaWalletService::test($request);
    }

    public function onReceiveTransaction(Request $request)
    {
        $coin = $request->data['currency'];

        logger()->info("Detect transaction {$coin}");

        return SotaWalletService::onReceiveTransaction($coin, $request->all());
    }
}
