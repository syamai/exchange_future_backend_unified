<?php

namespace SotaWallet\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SotaWallet\SotaWalletWebhook;

class ListWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sota_wallet_webhook:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show sota wallet blockchain webhooks';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $webhooks = SotaWalletWebhook::getWebhooks();
        $webhookCount = sizeof($webhooks);
        $this->info("Found $webhookCount webhook(s):");
        $hooked = false;
        if ($webhooks) {
            foreach ($webhooks as $webhook) {
                $this->info($webhook->url);
                if (Str::contains($webhook->url, env('APP_URL'))) {
                    $hooked = true;
                }
            }
        }
        if (!$hooked) {
            $this->warn('There is no webkook for this server');
        }
    }
}
