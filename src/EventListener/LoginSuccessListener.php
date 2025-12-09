<?php

declare(strict_types=1);

namespace App\EventListener;

use App\DTO\LoginThrottlingServiceInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
final class LoginSuccessListener
{
    public function __construct(
        private LoginThrottlingServiceInterface $loginThrottlingService
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($user && method_exists($user, 'getUserIdentifier')) {
            $email = $user->getUserIdentifier();
            $this->loginThrottlingService->clearFailedLoginAttempts($email);
        }
    }
}
