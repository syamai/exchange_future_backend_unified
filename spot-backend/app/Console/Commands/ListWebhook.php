<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\MasterdataService;
use Illuminate\Console\Command;

class ListWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:list {coin?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check blockchain webhook';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $coin = $this->argument('coin');
        if ($coin) {
            $this->checkWebhook($coin);
        } else {
            foreach (MasterdataService::getCoins() as $coin) {
                $this->checkWebhook($coin);
            }
        }
    }

    private function checkWebhook($coin)
    {
        $blockchainService = new SotatekBlockchainService($coin);
        $urls = $blockchainService->getWebhookList();
        $webhookCount = sizeof($urls);
        if ($webhookCount == 0) {
            $this->error("Found $webhookCount webhook for $coin:");
            $this->info("");
            return;
        } else {
            $this->info("Found $webhookCount webhook(s) for $coin:");
        }

        $appUrl = env('APP_URL');
        $hooked = false;
        foreach ($urls as $url) {
            $this->info($url);
            if (substr($url, 0, strlen($appUrl)) === $appUrl) {
                $hooked = true;
            }
        }
        if (!$hooked) {
            $this->comment("There is no webkook for this server");
        }
        $this->info("");
    }
}
