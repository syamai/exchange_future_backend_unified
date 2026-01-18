<?php

namespace App\Console\Commands;

use App\Services\StreamMatchingEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command to run the stream-based matching engine for a trading pair.
 *
 * Usage:
 *   php artisan matching-engine:stream usdt btc
 *   php artisan matching-engine:stream usdt eth --stats
 */
class RunStreamMatchingEngine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'matching-engine:stream
                            {currency : The quote currency (e.g., usdt)}
                            {coin : The base coin (e.g., btc)}
                            {--stats : Show statistics periodically}
                            {--stats-interval=30 : Statistics interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the stream-based matching engine for a specific trading pair';

    private ?StreamMatchingEngine $engine = null;
    private bool $showStats = false;
    private int $statsInterval = 30;
    private int $lastStatsTime = 0;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $currency = strtolower($this->argument('currency'));
        $coin = strtolower($this->argument('coin'));
        $this->showStats = $this->option('stats');
        $this->statsInterval = (int) $this->option('stats-interval');

        $symbol = strtoupper($coin . '/' . $currency);
        $this->info("Starting Stream Matching Engine for {$symbol}");

        // Register signal handlers for graceful shutdown
        $this->registerSignalHandlers();

        try {
            $this->engine = new StreamMatchingEngine($currency, $coin);
            $this->engine->initialize();

            $this->info("Matching engine initialized. Press Ctrl+C to stop.");
            $this->lastStatsTime = time();

            // Run the engine
            $this->runWithStats();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Matching engine error: " . $e->getMessage());
            Log::error("RunStreamMatchingEngine: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Run the engine with optional statistics output.
     *
     * @return void
     */
    private function runWithStats(): void
    {
        if (!$this->showStats) {
            $this->engine->run();
            return;
        }

        // Custom run loop with stats output
        $reflection = new \ReflectionObject($this->engine);

        // Access private running property
        $runningProp = $reflection->getProperty('running');
        $runningProp->setAccessible(true);
        $runningProp->setValue($this->engine, true);

        while ($runningProp->getValue($this->engine)) {
            // Call internal methods via reflection
            $this->invokePrivateMethod($this->engine, 'reclaimPendingMessages');

            $messages = $this->invokePrivateMethod($this->engine, 'readFromStream');
            if (!empty($messages)) {
                $this->invokePrivateMethod($this->engine, 'processMessages', [$messages]);
            }

            $this->invokePrivateMethod($this->engine, 'performMatching');

            // Output stats periodically
            if (time() - $this->lastStatsTime >= $this->statsInterval) {
                $this->outputStats();
                $this->lastStatsTime = time();
            }

            // Small sleep to prevent CPU spinning when idle
            usleep(1000); // 1ms
        }
    }

    /**
     * Invoke a private method on an object.
     *
     * @param object $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    private function invokePrivateMethod(object $object, string $methodName, array $args = [])
    {
        $reflection = new \ReflectionObject($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * Output engine statistics.
     *
     * @return void
     */
    private function outputStats(): void
    {
        $stats = $this->engine->getStats();
        $orderbook = $stats['orderbook'];

        $this->line('');
        $this->info('=== Matching Engine Stats ===');
        $this->line("Symbol: {$stats['coin']}/{$stats['currency']}");
        $this->line("Processed Orders: {$stats['processed_orders']}");
        $this->line("Matched Pairs: {$stats['matched_pairs']}");
        $this->line("Errors: {$stats['errors']}");
        $this->line("Buy Orders: {$orderbook['buy_orders']}");
        $this->line("Sell Orders: {$orderbook['sell_orders']}");

        if ($orderbook['best_bid'] && $orderbook['best_ask']) {
            $this->line("Best Bid: {$orderbook['best_bid']}");
            $this->line("Best Ask: {$orderbook['best_ask']}");
            $spread = $this->engine->getStats()['orderbook']['best_ask'] ?? 0;
        }

        $this->line('=============================');
    }

    /**
     * Register signal handlers for graceful shutdown.
     *
     * @return void
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function () {
            $this->info("\nReceived SIGINT, shutting down gracefully...");
            if ($this->engine) {
                $this->engine->stop();
            }
        });

        pcntl_signal(SIGTERM, function () {
            $this->info("\nReceived SIGTERM, shutting down gracefully...");
            if ($this->engine) {
                $this->engine->stop();
            }
        });
    }
}
