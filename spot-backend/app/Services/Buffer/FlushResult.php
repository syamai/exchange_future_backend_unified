<?php

namespace App\Services\Buffer;

/**
 * Result object for WriteBuffer flush operations.
 *
 * Provides detailed information about what was written to the database
 * during a flush operation.
 */
class FlushResult
{
    private int $ordersWritten = 0;
    private int $tradesWritten = 0;
    private int $balancesWritten = 0;
    private float $durationMs = 0.0;
    private array $errors = [];
    private bool $success = true;

    public function __construct(
        int $ordersWritten = 0,
        int $tradesWritten = 0,
        int $balancesWritten = 0,
        float $durationMs = 0.0,
        array $errors = []
    ) {
        $this->ordersWritten = $ordersWritten;
        $this->tradesWritten = $tradesWritten;
        $this->balancesWritten = $balancesWritten;
        $this->durationMs = $durationMs;
        $this->errors = $errors;
        $this->success = empty($errors);
    }

    public function getOrdersWritten(): int
    {
        return $this->ordersWritten;
    }

    public function getTradesWritten(): int
    {
        return $this->tradesWritten;
    }

    public function getBalancesWritten(): int
    {
        return $this->balancesWritten;
    }

    public function getTotalWritten(): int
    {
        return $this->ordersWritten + $this->tradesWritten + $this->balancesWritten;
    }

    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function toArray(): array
    {
        return [
            'orders_written' => $this->ordersWritten,
            'trades_written' => $this->tradesWritten,
            'balances_written' => $this->balancesWritten,
            'total_written' => $this->getTotalWritten(),
            'duration_ms' => $this->durationMs,
            'success' => $this->success,
            'errors' => $this->errors,
        ];
    }

    /**
     * Create an empty result (nothing to flush).
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create a failed result with error message.
     */
    public static function failed(string $error): self
    {
        return new self(0, 0, 0, 0.0, [$error]);
    }
}
