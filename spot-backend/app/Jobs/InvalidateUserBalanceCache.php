<?php

namespace App\Jobs;

use App\Consts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Async job to invalidate user balance cache and trigger WebSocket updates.
 * This decouples cache invalidation from the matching engine for better performance.
 */
class InvalidateUserBalanceCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $userId;
    private array $currencies;
    private ?string $store;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     *
     * @param int $userId
     * @param array $currencies
     * @param string|null $store
     */
    public function __construct(int $userId, array $currencies, ?string $store = null)
    {
        $this->userId = $userId;
        $this->currencies = $currencies;
        $this->store = $store;

        // Use cache queue for non-blocking cache operations
        $this->onQueue(Consts::QUEUE_CACHE ?? 'cache');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            // Invalidate balance cache for each currency
            foreach ($this->currencies as $currency) {
                $cacheKey = $this->getBalanceCacheKey($currency);
                Cache::forget($cacheKey);
            }

            // Trigger WebSocket balance update via existing mechanism
            SendBalance::dispatchIfNeed($this->userId, $this->currencies, $this->store);

        } catch (\Exception $e) {
            Log::error("InvalidateUserBalanceCache failed for user {$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the cache key for user balance.
     *
     * @param string $currency
     * @return string
     */
    private function getBalanceCacheKey(string $currency): string
    {
        return "user_balance:{$this->userId}:{$currency}";
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("InvalidateUserBalanceCache permanently failed for user {$this->userId}: " . $exception->getMessage());
    }
}
