<?php

namespace SotaWallet\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use SotaWallet\SotaWalletWebhook;

class RemoveWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sota_wallet_webhook:remove {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove sota wallet blockchain webhooks';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $url = $this->getUrl();
        $response = SotaWalletWebhook::removeWebhook($url);
        $this->info('Server reponse: ' . $response);
        if ($this->confirm('Do you want to continue?')) {
            $this->call('sota_wallet_webhook:remove');
        }
    }

    private function getUrl(): bool|array|string|null
    {
        $url = $this->argument('url');
        if (!$url) {
            $url = $this->choice('What webhook do you want to remove?', Arr::pluck(SotaWalletWebhook::getWebhooks(), 'url'));
        }
        return $url;
    }
}
