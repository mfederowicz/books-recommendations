<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\LoginThrottlingServiceInterface;
use App\Entity\User;
use App\Form\RegisterUserAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuthController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private UserAuthenticatorInterface $userAuthenticator;
    private LoginThrottlingServiceInterface $loginThrottlingService;
    private TranslatorInterface $translator;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserAuthenticatorInterface $userAuthenticator,
        LoginThrottlingServiceInterface $loginThrottlingService,
        TranslatorInterface $translator,
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->userAuthenticator = $userAuthenticator;
        $this->loginThrottlingService = $loginThrottlingService;
        $this->translator = $translator;
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(RegisterUserAccount::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $form->get('password')->getData()
            );
            $user->setPasswordHash($hashedPassword);

            // Save the user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Add success flash message and redirect to homepage
            $this->addFlash('success', $this->translator->trans('flash.registration_success'));

            return $this->redirectToRoute('homepage');
        }

        return $this->render('auth/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Check if user is blocked due to failed login attempts
        $throttlingError = null;
        if ($lastUsername && $this->loginThrottlingService->isUserBlocked($lastUsername)) {
            $blockedUntil = $this->loginThrottlingService->getBlockedUntil($lastUsername);
            $throttlingError = $this->translator->trans('auth.throttling.blocked', [
                '%time%' => $blockedUntil ? $blockedUntil->format('H:i:s') : 'pewnym czasie',
            ]);
        }

        // If there was a login error, record the failed attempt
        if ($error && $lastUsername) {
            $this->loginThrottlingService->recordFailedLoginAttempt($lastUsername, $request->getClientIp());
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'throttling_error' => $throttlingError,
        ]);
    }
}
