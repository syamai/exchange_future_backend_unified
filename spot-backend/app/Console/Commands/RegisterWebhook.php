<?php

namespace App\Console\Commands;

use App\Consts;
use App\Http\Services\Blockchain\SotatekBlockchainService;
use App\Http\Services\MasterdataService;
use Illuminate\Console\Command;

class RegisterWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:register {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register webhook';

    private $blockchainService;



    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $this->blockchainService = new SotatekBlockchainService('');
        $url = $this->getUrl();
        $response = $this->blockchainService->registerWebhook($url);
        $this->info('Server reponse: ' . $response);
        if ($this->confirm("Do you want to continue?")) {
            $this->call('webhook:register');
        }
    }

    private function getUrl()
    {
        $url = $this->argument('url');
        if (!$url) {
            $defaultUrl = env('APP_URL') . $this->blockchainService->getDefaultWebhookUrl();
            $url = $this->ask("Webhook url ($defaultUrl)");
        }
        return $url;
    }
}
