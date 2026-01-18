<?php

namespace SotaWallet;

use Route;
use Illuminate\Support\ServiceProvider;
use SotaWallet\Commands\ListWebhook;
use SotaWallet\Commands\RegisterWebhook;
use SotaWallet\Commands\RemoveWebhook;
use SotaWallet\Commands\SyncWebhook;

class SotaWalletProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfigs();
        $this->registerApiRoutes();
        $this->loadCommands();
    }

    private function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListWebhook::class,
                RegisterWebhook::class,
                RemoveWebhook::class,
                SyncWebhook::class,
            ]);
        }
    }

    private function publishConfigs()
    {
        $this->publishes([
            __DIR__.'/config/coin.php' => config_path('coin.php'),
            __DIR__.'/config/webhook.php' => config_path('webhook.php'),
        ], 'sota_wallet:config');
    }

    protected function registerApiRoutes()
    {
        Route::group(['prefix' => 'api/webhook', 'middleware' => 'api', 'namespace' => 'SotaWallet'], function () {
            Route::post('sotatek', 'WebhookController@onReceiveTransaction');
        });
    }
}
