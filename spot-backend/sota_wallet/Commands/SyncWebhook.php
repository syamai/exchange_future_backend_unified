<?php

namespace SotaWallet\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SotaWallet\SotaWalletService;

class SyncWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sota_wallet_webhook:sync {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync sota wallet blockchain webhooks';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $interestingWebhooks = $this->getInterestingWebhooks();
        $this->info('The list of needed Webhooks');
        $this->printWebhooks($interestingWebhooks);


        $registedWebhooks = $this->fetchRegistedWebhook();
        $this->info('The list of registed webhooks');
        $this->printWebhooks($registedWebhooks);

        $this->info("\n\n\n");

        $needRegisterWebhooks = $this->getNeedModifyWebhooks($interestingWebhooks, $registedWebhooks);
        if (count($needRegisterWebhooks)) {
            $this->info('The list of webhooks need to be registered');
            $this->printWebhooks($needRegisterWebhooks);
            if ($this->option('force') || $this->confirm('Do you want to register those webhooks?')) {
                $this->registerWebhooks($needRegisterWebhooks);
            }
        } else {
            $this->info('No webhooks need to be registered!');
        }

        $this->info('');

        $needRemoveWebhooks = $this->getNeedModifyWebhooks($registedWebhooks, $interestingWebhooks);
        if (count($needRemoveWebhooks)) {
            $this->info('The list of webhooks need to be removed');
            $this->printWebhooks($needRemoveWebhooks);
            if ($this->option('force') || $this->confirm('Do you want to remove those webhooks?')) {
                $this->removeWebhooks($needRemoveWebhooks);
            }
        } else {
            $this->info('No webhooks need to be removed!');
        }

        $this->info('');
    }

    public function getNeedModifyWebhooks($base, $quote)
    {
        $base = $base->map(function ($webhook) {
            return json_encode($webhook);
        });

        $quote = $quote->map(function ($webhook) {
            return json_encode($webhook);
        });

        $sharedWebhooks = $base->intersect($quote);
        return $base->diff($sharedWebhooks)->map(function ($webhook) {
            return json_decode($webhook, true);
        });
    }

    public function printWebhooks($webhooks)
    {
        $headers = ['coin', 'type', 'url'];
        $this->table($headers, $webhooks->toArray());
    }

    public function registerWebhooks($webhooks)
    {
        $bar = $this->output->createProgressBar(count($webhooks));
        foreach ($webhooks as $webhook) {
            SotaWalletService::registerWebhook($webhook['type'], $webhook['coin'], $webhook['url']);
            $bar->advance();
        };
        $bar->finish();
    }

    public function removeWebhooks($webhooks)
    {
        $bar = $this->output->createProgressBar(count($webhooks));
        foreach ($webhooks as $webhook) {
            SotaWalletService::removeWebhook($webhook['coin'], $webhook['type'], $webhook['url']);
            $bar->advance();
        };
        $bar->finish();
    }

    public function getInterestingWebhooks()
    {
        $types = SotaWalletService::getWebhookTypes();
        $coins = collect(config('sota-wallet.coins'))->intersect(SotaWalletService::getAllCoins())->all();

        $webhooks = [];

        foreach ($coins as $coin) {
            foreach ($types as $type) {
                $webhooks[] = [
                    'type' => $type,
                    'coin' => $coin,
                    'url' => env('APP_URL') . SotaWalletService::getDefaultWebhookUrl($type, $coin),
                ];
            }
        }

        return collect($webhooks);
    }

    public function fetchRegistedWebhook()
    {
        $coins = SotaWalletService::getAllCoins();

        $registedWebhooks = [];
        foreach ($coins as $coin) {
            $webhooks = SotaWalletService::getWebhooks(false, $coin) ?? [];
            $webhooks = collect($webhooks)->filter(function ($webhook) {
                return Str::contains($webhook->url, env('APP_URL'));
            })->map(function ($webhook) {
                return [
                    'type' => $webhook->type,
                    'coin' => $webhook->coin,
                    'url' => $webhook->url,
                ];
            })->all();
            $registedWebhooks = collect($registedWebhooks)->concat($webhooks)->all();
        }
        return collect($registedWebhooks);
    }
}
