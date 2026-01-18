<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Http\Services\BotTelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BotTelegramAPIController extends AppBaseController
{
    private $botTelegramService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->botTelegramService = new BotTelegramService();
    }

    /**
     * @param Request $request
     * //Create button set link on app Android and IOS: https://telegram.me/AmanpuriBot?start=
     * //Set Web hook: https://api.telegram.org:443/bot683034804:AAElnFzht0XvCRQ8iry-QDTT076XVZXcFtA/setwebhook?url=https://d2f7e5b4.ngrok.io/api/startBot
     */
    public function startBot(Request $request)
    {
        $this->botTelegramService->setChatIdApi($request->all());
    }
}
