<?php

namespace App\Console\Commands;

use App\Services\SwooleMatchingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Swoole\Coroutine;
use Swoole\Runtime;

/**
 * Artisan command to run the Swoole-based matching engine for a trading pair.
 *
 * Usage:
 *   php artisan matching-engine:swoole usdt btc
 *   php artisan matching-engine:swoole usdt eth --workers=8
 */
class RunSwooleMatchingEngine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'matching-engine:swoole
                            {currency : The quote currency (e.g., usdt)}
                            {coin : The base coin (e.g., btc)}
                            {--workers=4 : Number of matching worker coroutines}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Swoole coroutine-based matching engine for high-performance order processing';

    private ?SwooleMatchingEngine $engine = null;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // Check Swoole extension
        if (!extension_loaded('swoole')) {
            $this->error('Swoole extension is not installed. Please install it first.');
            $this->line('Run: pecl install swoole');
            return Command::FAILURE;
        }

        $currency = strtolower($this->argument('currency'));
        $coin = strtolower($this->argument('coin'));
        $workers = (int) $this->option('workers');

        $symbol = strtoupper($coin . '/' . $currency);
        $this->info("Starting Swoole Matching Engine for {$symbol}");
        $this->info("Workers: {$workers}");
        $this->info("Swoole version: " . swoole_version());

        // Enable coroutine hooks for PDO, Redis, etc.
        $this->enableCoroutineHooks();

        // Register signal handlers
        $this->registerSignalHandlers();

        try {
            // Run in Swoole coroutine context
            Coroutine\run(function () use ($currency, $coin) {
                $this->engine = new SwooleMatchingEngine($currency, $coin);
                $this->engine->start();
            });

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Swoole Matching Engine error: " . $e->getMessage());
            Log::error("RunSwooleMatchingEngine: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Enable Swoole coroutine hooks for async I/O.
     *
     * @return void
     */
    private function enableCoroutineHooks(): void
    {
        // Use SWOOLE_HOOK_ALL for maximum compatibility across Swoole versions
        // This enables all available hooks including TCP, UDP, SSL, File, PDO, etc.
        // Note: Excludes SWOOLE_HOOK_CURL by default to avoid conflicts
        $flags = SWOOLE_HOOK_ALL;

        // Optionally exclude PDO_MYSQL hook if it causes issues
        // $flags = SWOOLE_HOOK_ALL ^ SWOOLE_HOOK_PDO_MYSQL;

        Runtime::enableCoroutine($flags);

        $this->info("Swoole coroutine hooks enabled (SWOOLE_HOOK_ALL)");
    }

    /**
     * Register signal handlers for graceful shutdown.
     *
     * @return void
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            $this->warn('pcntl extension not available, signal handling disabled');
            return;
        }

        pcntl_async_signals(true);

        $handler = function (int $signal) {
            $signalName = $signal === SIGINT ? 'SIGINT' : 'SIGTERM';
            $this->info("\nReceived {$signalName}, shutting down gracefully...");

            if ($this->engine) {
                $this->engine->stop();

                // Output final stats
                $stats = $this->engine->getStats();
                $this->outputFinalStats($stats);
            }
        };

        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);

        $this->info("Signal handlers registered (SIGINT, SIGTERM)");
    }

    /**
     * Output final statistics on shutdown.
     *
     * @param array $stats
     * @return void
     */
    private function outputFinalStats(array $stats): void
    {
        $this->line('');
        $this->info('=== Final Statistics ===');
        $this->line("Symbol: {$stats['coin']}/{$stats['currency']}");
        $this->line("Uptime: {$stats['uptime_sec']} seconds");
        $this->line("Received Orders: {$stats['received_orders']}");
        $this->line("Matched Pairs: {$stats['matched_pairs']}");
        $this->line("DB Writes: {$stats['db_writes']}");
        $this->line("Errors: {$stats['errors']}");
        $this->line("Average TPS: {$stats['tps']}");

        if (isset($stats['orderbook'])) {
            $ob = $stats['orderbook'];
            $this->line("Final Buy Orders: {$ob['buy_orders']}");
            $this->line("Final Sell Orders: {$ob['sell_orders']}");
        }

        $this->info('========================');
    }
}
