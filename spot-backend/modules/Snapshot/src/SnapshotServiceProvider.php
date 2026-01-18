<?php

namespace Snapshot;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Snapshot\Console\Commands\SnapshotTakeProfitCommand;

class SnapshotServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routers/admin.php');
        $this->mergeConfigFrom(__DIR__ . '/../config/snapshot.php', 'snapshot');

        $this->commands([
            SnapshotTakeProfitCommand::class
        ]);

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('snapshot:take-profit')->cron(config('snapshot.cron', '0 * * * *'));
        });
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
