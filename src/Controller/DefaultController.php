<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index(): Response
    {
        if ($this->getUser()) {
            // Logged in user - show dashboard
            return $this->render('dashboard.html.twig');
        }

        // Not logged in - show landing page
        return $this->render('homepage.html.twig');
    }

    /**
     * Handle .well-known requests (Chrome DevTools, etc.).
     */
    public function wellKnown(): Response
    {
        return new Response('', 404);
    }

    #[Route('/debug/auth', name: 'debug_auth', methods: ['GET'])]
    public function debugAuth(Request $request): Response
    {
        $user = $this->getUser();
        $token = $this->container->get('security.token_storage')->getToken();
        $session = $request->getSession();

        $debug = [
            'user_from_getUser' => $user ? $user->getEmail() : null,
            'user_from_app' => $this->get('twig')->getGlobals()['app']->getUser() ? $this->get('twig')->getGlobals()['app']->getUser()->getEmail() : null,
            'token_exists' => $token !== null,
            'token_class' => $token ? get_class($token) : null,
            'token_user' => $token && $token->getUser() ? $token->getUser()->getEmail() : null,
            'is_authenticated' => $this->isGranted('IS_AUTHENTICATED_FULLY'),
            'session_id' => $session->getId(),
            'session_data' => $session->all(),
            'server_vars' => [
                'REQUEST_METHOD' => $request->server->get('REQUEST_METHOD'),
                'HTTP_HOST' => $request->server->get('HTTP_HOST'),
                'REQUEST_URI' => $request->server->get('REQUEST_URI'),
                'REMOTE_ADDR' => $request->server->get('REMOTE_ADDR'),
                'HTTP_USER_AGENT' => $request->server->get('HTTP_USER_AGENT'),
            ],
            'cookies' => $request->cookies->all(),
            'headers' => [
                'cookie' => $request->headers->get('cookie'),
                'user-agent' => $request->headers->get('user-agent'),
                'host' => $request->headers->get('host'),
            ],
        ];

        return $this->render('debug/auth.html.twig', [
            'debug' => $debug,
        ]);
    }
}
