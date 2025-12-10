<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\DTO\LoginThrottlingServiceInterface;
use App\EventListener\LoginSuccessListener;
use PHPUnit\Framework\TestCase;

class LoginSuccessListenerTest extends TestCase
{
    private LoginThrottlingServiceInterface $loginThrottlingService;
    private LoginSuccessListener $listener;

    protected function setUp(): void
    {
        $this->loginThrottlingService = $this->createMock(LoginThrottlingServiceInterface::class);
        $this->listener = new LoginSuccessListener($this->loginThrottlingService);
    }

    public function testListenerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(LoginSuccessListener::class, $this->listener);
    }

    public function testListenerHasRequiredDependencies(): void
    {
        // Test that the listener can be created with proper dependencies
        $listener = new LoginSuccessListener($this->loginThrottlingService);

        $this->assertInstanceOf(LoginSuccessListener::class, $listener);
    }

    // Note: Full testing of event handling requires integration tests
    // with real Symfony event system. These unit tests verify the class can be instantiated.
}
