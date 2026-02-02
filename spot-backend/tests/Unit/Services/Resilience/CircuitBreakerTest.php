<?php

namespace Tests\Unit\Services\Resilience;

use App\Exceptions\CircuitOpenException;
use App\Services\Resilience\CircuitBreaker;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->circuitBreaker = new CircuitBreaker('test');
    }

    /** @test */
    public function it_starts_in_closed_state(): void
    {
        $this->assertEquals('CLOSED', $this->circuitBreaker->getState());
        $this->assertTrue($this->circuitBreaker->isClosed());
    }

    /** @test */
    public function it_executes_action_when_closed(): void
    {
        // Act
        $result = $this->circuitBreaker->execute(fn() => 'success');

        // Assert
        $this->assertEquals('success', $result);
        $this->assertEquals('CLOSED', $this->circuitBreaker->getState());
    }

    /** @test */
    public function it_opens_after_threshold_failures(): void
    {
        // Arrange - cause 5 failures (threshold)
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->execute(function () {
                    throw new \RuntimeException('Test failure');
                });
            } catch (\RuntimeException $e) {
                // Expected
            }
        }

        // Assert
        $this->assertEquals('OPEN', $this->circuitBreaker->getState());
        $this->assertTrue($this->circuitBreaker->isOpen());
    }

    /** @test */
    public function it_rejects_requests_when_open(): void
    {
        // Arrange - open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->execute(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
            }
        }

        // Act & Assert
        $this->expectException(CircuitOpenException::class);
        $this->circuitBreaker->execute(fn() => 'should not execute');
    }

    /** @test */
    public function it_uses_fallback_when_open(): void
    {
        // Arrange - open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->execute(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
            }
        }

        // Act
        $result = $this->circuitBreaker->execute(
            fn() => 'primary',
            fn() => 'fallback'
        );

        // Assert
        $this->assertEquals('fallback', $result);
    }

    /** @test */
    public function it_resets_failure_count_on_success(): void
    {
        // Arrange - cause some failures (but not enough to open)
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
            }
        }

        // Act - successful execution
        $this->circuitBreaker->execute(fn() => 'success');

        // Arrange - cause more failures
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->execute(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
            }
        }

        // Assert - circuit should still be closed (failures reset)
        $this->assertEquals('CLOSED', $this->circuitBreaker->getState());
    }

    /** @test */
    public function it_can_be_manually_reset(): void
    {
        // Arrange - open the circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->execute(fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException $e) {
            }
        }
        $this->assertEquals('OPEN', $this->circuitBreaker->getState());

        // Act
        $this->circuitBreaker->reset();

        // Assert
        $this->assertEquals('CLOSED', $this->circuitBreaker->getState());
    }

    /** @test */
    public function it_returns_stats(): void
    {
        // Act
        $stats = $this->circuitBreaker->getStats();

        // Assert
        $this->assertArrayHasKey('name', $stats);
        $this->assertArrayHasKey('state', $stats);
        $this->assertArrayHasKey('failure_count', $stats);
        $this->assertArrayHasKey('failure_threshold', $stats);
        $this->assertEquals('test', $stats['name']);
    }
}
