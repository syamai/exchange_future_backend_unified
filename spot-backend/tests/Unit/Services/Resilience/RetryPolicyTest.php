<?php

namespace Tests\Unit\Services\Resilience;

use App\Services\Resilience\RetryPolicy;
use Tests\TestCase;

class RetryPolicyTest extends TestCase
{
    /** @test */
    public function it_executes_action_successfully_without_retry(): void
    {
        // Arrange
        $policy = new RetryPolicy();
        $callCount = 0;

        // Act
        $result = $policy->execute(function () use (&$callCount) {
            $callCount++;
            return 'success';
        });

        // Assert
        $this->assertEquals('success', $result);
        $this->assertEquals(1, $callCount);
    }

    /** @test */
    public function it_retries_on_failure(): void
    {
        // Arrange
        $policy = new RetryPolicy(maxRetries: 3);
        $callCount = 0;

        // Act
        $result = $policy->execute(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new \RuntimeException('Temporary failure');
            }
            return 'success';
        });

        // Assert
        $this->assertEquals('success', $result);
        $this->assertEquals(3, $callCount);
    }

    /** @test */
    public function it_throws_after_max_retries(): void
    {
        // Arrange
        $policy = new RetryPolicy(maxRetries: 2);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $policy->execute(function () {
            throw new \RuntimeException('Persistent failure');
        });
    }

    /** @test */
    public function it_calculates_exponential_delay(): void
    {
        // Arrange
        $policy = new RetryPolicy(baseDelayMs: 100, maxDelayMs: 10000);

        // Act & Assert
        // Attempt 0: 100 * 2^0 = 100ms (±jitter)
        $delay0 = $policy->getDelay(0);
        $this->assertGreaterThanOrEqual(90, $delay0);
        $this->assertLessThanOrEqual(110, $delay0);

        // Attempt 1: 100 * 2^1 = 200ms (±jitter)
        $delay1 = $policy->getDelay(1);
        $this->assertGreaterThanOrEqual(180, $delay1);
        $this->assertLessThanOrEqual(220, $delay1);

        // Attempt 2: 100 * 2^2 = 400ms (±jitter)
        $delay2 = $policy->getDelay(2);
        $this->assertGreaterThanOrEqual(360, $delay2);
        $this->assertLessThanOrEqual(440, $delay2);
    }

    /** @test */
    public function it_caps_delay_at_max(): void
    {
        // Arrange
        $policy = new RetryPolicy(baseDelayMs: 100, maxDelayMs: 500);

        // Act - attempt 10 would be 100 * 2^10 = 102400ms without cap
        $delay = $policy->getDelay(10);

        // Assert - should be capped at ~500ms (±jitter)
        $this->assertLessThanOrEqual(550, $delay);
    }

    /** @test */
    public function it_only_retries_retryable_exceptions(): void
    {
        // Arrange
        $policy = new RetryPolicy();
        $callCount = 0;

        // Act & Assert - InvalidArgumentException is not retryable
        $this->expectException(\InvalidArgumentException::class);

        $policy->execute(
            function () use (&$callCount) {
                $callCount++;
                throw new \InvalidArgumentException('Not retryable');
            },
            [\RuntimeException::class] // Only retry RuntimeException
        );
    }

    /** @test */
    public function it_reports_should_retry_correctly(): void
    {
        $policy = new RetryPolicy(maxRetries: 3);

        $this->assertTrue($policy->shouldRetry(0));
        $this->assertTrue($policy->shouldRetry(1));
        $this->assertTrue($policy->shouldRetry(2));
        $this->assertFalse($policy->shouldRetry(3));
    }
}
