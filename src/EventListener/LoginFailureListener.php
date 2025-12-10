<?php

declare(strict_types=1);

namespace App\EventListener;

use App\DTO\LoginThrottlingServiceInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\AuthenticationFailureEvent;

#[AsEventListener(event: AuthenticationFailureEvent::class)]
final class LoginFailureListener
{
    public function __construct(
        private LoginThrottlingServiceInterface $loginThrottlingService,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        $email = $request->request->get('_username') ?? $request->request->get('email');
        $ipAddress = $request->getClientIp();

        if (null === $email || !is_string($email)) {
            return;
        }

        $this->loginThrottlingService->recordFailedLoginAttempt($email, $ipAddress);
    }
}
