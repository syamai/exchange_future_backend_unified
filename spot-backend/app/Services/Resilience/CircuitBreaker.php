<?php

namespace App\Services\Resilience;

use App\Exceptions\CircuitOpenException;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker pattern implementation (FR-RL-001)
 *
 * Protects the system from cascading failures by:
 * - Detecting consecutive failures
 * - Opening the circuit to reject requests immediately
 * - Periodically testing if the service has recovered
 *
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Failure detected, requests rejected immediately
 * - HALF_OPEN: Testing recovery, limited requests allowed
 */
class CircuitBreaker
{
    // FR-RL-001 thresholds
    private const FAILURE_THRESHOLD = 5;
    private const RECOVERY_TIMEOUT = 30; // seconds
    private const HALF_OPEN_TEST_REQUESTS = 3;

    private string $name;
    private string $state = 'CLOSED';
    private int $failureCount = 0;
    private int $successCount = 0;
    private ?int $lastFailureTime = null;
    private ?int $stateChangedAt = null;

    public function __construct(string $name = 'default')
    {
        $this->name = $name;
        $this->stateChangedAt = time();
    }

    /**
     * Execute an action through the circuit breaker.
     *
     * @param callable $action The action to execute
     * @param callable|null $fallback Optional fallback when circuit is open
     * @return mixed
     * @throws CircuitOpenException
     */
    public function execute(callable $action, ?callable $fallback = null): mixed
    {
        if (!$this->canExecute()) {
            Log::warning("CircuitBreaker [{$this->name}]: Circuit is OPEN, rejecting request");

            if ($fallback) {
                return $fallback();
            }

            throw new CircuitOpenException("Circuit breaker [{$this->name}] is open");
        }

        try {
            $result = $action();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure($e);
            throw $e;
        }
    }

    /**
     * Check if action can be executed based on circuit state.
     */
    private function canExecute(): bool
    {
        switch ($this->state) {
            case 'CLOSED':
                return true;

            case 'OPEN':
                // Check if recovery timeout has passed
                if ($this->lastFailureTime &&
                    (time() - $this->lastFailureTime) >= self::RECOVERY_TIMEOUT) {
                    $this->transitionTo('HALF_OPEN');
                    return true;
                }
                return false;

            case 'HALF_OPEN':
                // Allow limited test requests
                return true;

            default:
                return false;
        }
    }

    /**
     * Handle successful execution.
     */
    private function onSuccess(): void
    {
        switch ($this->state) {
            case 'CLOSED':
                $this->failureCount = 0;
                break;

            case 'HALF_OPEN':
                $this->successCount++;
                if ($this->successCount >= self::HALF_OPEN_TEST_REQUESTS) {
                    $this->transitionTo('CLOSED');
                }
                break;
        }
    }

    /**
     * Handle failed execution.
     */
    private function onFailure(\Exception $e): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        Log::warning("CircuitBreaker [{$this->name}]: Failure recorded", [
            'failure_count' => $this->failureCount,
            'threshold' => self::FAILURE_THRESHOLD,
            'state' => $this->state,
            'error' => $e->getMessage(),
        ]);

        switch ($this->state) {
            case 'CLOSED':
                if ($this->failureCount >= self::FAILURE_THRESHOLD) {
                    $this->transitionTo('OPEN');
                }
                break;

            case 'HALF_OPEN':
                // Any failure in HALF_OPEN goes back to OPEN
                $this->transitionTo('OPEN');
                break;
        }
    }

    /**
     * Transition to a new state.
     */
    private function transitionTo(string $newState): void
    {
        $oldState = $this->state;
        $this->state = $newState;
        $this->stateChangedAt = time();

        Log::info("CircuitBreaker [{$this->name}]: State transition", [
            'from' => $oldState,
            'to' => $newState,
        ]);

        // Reset counters on state change
        if ($newState === 'CLOSED') {
            $this->failureCount = 0;
            $this->successCount = 0;
        } elseif ($newState === 'HALF_OPEN') {
            $this->successCount = 0;
        }
    }

    /**
     * Manually reset the circuit breaker.
     */
    public function reset(): void
    {
        $this->transitionTo('CLOSED');
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->lastFailureTime = null;
    }

    /**
     * Get current circuit state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Check if circuit is closed (healthy).
     */
    public function isClosed(): bool
    {
        return $this->state === 'CLOSED';
    }

    /**
     * Check if circuit is open (unhealthy).
     */
    public function isOpen(): bool
    {
        return $this->state === 'OPEN';
    }

    /**
     * Get circuit breaker statistics.
     */
    public function getStats(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'failure_threshold' => self::FAILURE_THRESHOLD,
            'recovery_timeout' => self::RECOVERY_TIMEOUT,
            'last_failure_time' => $this->lastFailureTime,
            'state_changed_at' => $this->stateChangedAt,
            'time_in_state' => time() - $this->stateChangedAt,
        ];
    }
}
