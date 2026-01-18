<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\MasterdataService;
use Illuminate\Console\Command;

class RemoveWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:remove {coin?} {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove webhook';



    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle()
    {
        $coin = $this->getCoin();
        $blockchainService = new SotatekBlockchainService($coin);
        $url = $this->getUrl($coin);
        $response = $blockchainService->removeWebhook($url);
        $this->info('Server reponse: ' . $response);
        if ($this->confirm("Do you want to continue?")) {
            $this->call('webhook:remove');
        }
    }

    private function getCoin()
    {
        $coin = $this->argument('coin');
        if (!$coin) {
            $coins = implode(", ", MasterdataService::getCoins());
            $coin = $this->ask("Coin ($coins)");
        }
        return $coin;
    }

    private function getUrl($coin)
    {
        $url = $this->argument('url');
        if (!$url) {
            $this->call('webhook:list', [
                'coin' => $coin
            ]);
            $url = $this->ask("What webhook do you want to remove");
        }
        return $url;
    }
}
