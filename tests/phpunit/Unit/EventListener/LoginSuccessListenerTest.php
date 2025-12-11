<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\DTO\LoginThrottlingServiceInterface;
use App\EventListener\LoginSuccessListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

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

    public function testInvokeClearsFailedLoginAttempts(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('test@example.com');

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->loginThrottlingService->expects($this->once())
            ->method('clearFailedLoginAttempts')
            ->with('test@example.com');

        $this->listener->__invoke($event);
    }

    public function testInvokeSkipsWhenUserHasEmptyIdentifier(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('');

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->loginThrottlingService->expects($this->never())
            ->method('clearFailedLoginAttempts');

        $this->listener->__invoke($event);
    }

    public function testInvokeSkipsWhenUserHasNoIdentifierMethod(): void
    {
        // Create a partial mock that doesn't have getUserIdentifier method
        $user = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $event = $this->createMock(LoginSuccessEvent::class);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->loginThrottlingService->expects($this->never())
            ->method('clearFailedLoginAttempts');

        $this->listener->__invoke($event);
    }
}
