<?php

namespace App\Services\Resilience;

use Illuminate\Support\Facades\Log;

/**
 * Exponential backoff retry policy (FR-RL-002)
 *
 * Implements retry logic with:
 * - Exponential delay increase: delay = base * 2^attempt
 * - Jitter to prevent thundering herd
 * - Maximum delay cap
 */
class RetryPolicy
{
    // FR-RL-002 configuration
    private const MAX_RETRIES = 3;
    private const BASE_DELAY_MS = 100;
    private const MAX_DELAY_MS = 30000;
    private const JITTER_FACTOR = 0.1; // 10% jitter

    private int $maxRetries;
    private int $baseDelayMs;
    private int $maxDelayMs;

    public function __construct(
        int $maxRetries = self::MAX_RETRIES,
        int $baseDelayMs = self::BASE_DELAY_MS,
        int $maxDelayMs = self::MAX_DELAY_MS
    ) {
        $this->maxRetries = $maxRetries;
        $this->baseDelayMs = $baseDelayMs;
        $this->maxDelayMs = $maxDelayMs;
    }

    /**
     * Execute an action with retry logic.
     *
     * @param callable $action The action to execute
     * @param array $retryableExceptions Exception classes that should trigger retry
     * @return mixed
     * @throws \Exception The last exception if all retries fail
     */
    public function execute(callable $action, array $retryableExceptions = [\Exception::class]): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $action();
            } catch (\Exception $e) {
                $lastException = $e;

                // Check if exception is retryable
                if (!$this->isRetryable($e, $retryableExceptions)) {
                    throw $e;
                }

                // Don't sleep after last attempt
                if ($attempt < $this->maxRetries) {
                    $delay = $this->getDelay($attempt);

                    Log::warning("RetryPolicy: Attempt {$attempt} failed, retrying in {$delay}ms", [
                        'attempt' => $attempt,
                        'max_retries' => $this->maxRetries,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($delay * 1000); // Convert ms to microseconds
                }
            }
        }

        Log::error("RetryPolicy: All retries exhausted", [
            'max_retries' => $this->maxRetries,
            'error' => $lastException?->getMessage(),
        ]);

        throw $lastException;
    }

    /**
     * Calculate delay for given attempt with exponential backoff and jitter.
     *
     * @param int $attempt Zero-based attempt number
     * @return int Delay in milliseconds
     */
    public function getDelay(int $attempt): int
    {
        // Exponential backoff: base * 2^attempt
        $delay = $this->baseDelayMs * pow(2, $attempt);

        // Cap at max delay
        $delay = min($delay, $this->maxDelayMs);

        // Add jitter (±10%)
        $jitter = (int) ($delay * self::JITTER_FACTOR);
        $delay += random_int(-$jitter, $jitter);

        return max(0, $delay);
    }

    /**
     * Check if exception should trigger a retry.
     */
    private function isRetryable(\Exception $e, array $retryableExceptions): bool
    {
        foreach ($retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get maximum number of retries.
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Check if should retry based on attempt number.
     */
    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxRetries;
    }
}
