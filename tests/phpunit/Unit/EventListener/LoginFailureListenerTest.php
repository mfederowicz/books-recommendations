<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\DTO\LoginThrottlingServiceInterface;
use App\EventListener\LoginFailureListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

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

    public function testInvokeRecordsFailedLoginAttempt(): void
    {
        $request = Request::create('/', 'POST', ['_username' => 'test@example.com']);
        $request = $request->duplicate(server: ['REMOTE_ADDR' => '127.0.0.1']);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $listener = new LoginFailureListener($this->loginThrottlingService, $requestStack);

        $this->loginThrottlingService->expects($this->once())
            ->method('recordFailedLoginAttempt')
            ->with('test@example.com', '127.0.0.1');

        $event = $this->createMock(LoginFailureEvent::class);
        $listener($event);
    }

    public function testInvokeSkipsWhenNoRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $listener = new LoginFailureListener($this->loginThrottlingService, $requestStack);

        $this->loginThrottlingService->expects($this->never())
            ->method('recordFailedLoginAttempt');

        $event = $this->createMock(LoginFailureEvent::class);
        $listener($event);
    }

    public function testInvokeSkipsWhenNoEmail(): void
    {
        $request = Request::create('/', 'POST'); // No _username parameter

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $listener = new LoginFailureListener($this->loginThrottlingService, $requestStack);

        $this->loginThrottlingService->expects($this->never())
            ->method('recordFailedLoginAttempt');

        $event = $this->createMock(LoginFailureEvent::class);
        $listener($event);
    }
}
