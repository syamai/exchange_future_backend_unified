<?php

namespace SotaWallet\Commands;

use Illuminate\Console\Command;
use SotaWallet\SotaWalletWebhook;

class RegisterWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sota_wallet_webhook:register {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register sota wallet blockchain webhooks';



    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {
        $url = $this->getUrl();

        $response = SotaWalletWebhook::registerWebhook($url);
        $this->info('Server reponse: ' . $response);

        if ($this->confirm('Do you want to continue?')) {
            $this->call('sota_wallet_webhook:register');
        }
    }

    private function getUrl()
    {
        $url = $this->argument('url');
        if (!$url) {
            $defaultUrl = env('APP_URL') . SotaWalletWebhook::getDefaultWebhookUrl();
            $url = $this->ask("Webhook url ($defaultUrl)");
        }
        return $url;
    }
}
