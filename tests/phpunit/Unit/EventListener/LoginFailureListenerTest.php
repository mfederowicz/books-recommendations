<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\DTO\LoginThrottlingServiceInterface;
use App\EventListener\LoginFailureListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class LoginFailureListenerTest extends TestCase
{
    private LoginThrottlingServiceInterface $loginThrottlingService;
    private RequestStack $requestStack;
    private LoginFailureListener $listener;

    protected function setUp(): void
    {
        $this->loginThrottlingService = $this->createMock(LoginThrottlingServiceInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->listener = new LoginFailureListener($this->loginThrottlingService, $this->requestStack);
    }

    public function testListenerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(LoginFailureListener::class, $this->listener);
    }

    public function testListenerHasRequiredDependencies(): void
    {
        // Test that the listener can be created with proper dependencies
        $listener = new LoginFailureListener(
            $this->loginThrottlingService,
            $this->requestStack
        );

        $this->assertInstanceOf(LoginFailureListener::class, $listener);
    }

    // Note: Full testing of event handling requires integration tests
    // with real Symfony event system. These unit tests verify the class can be instantiated.
}
