<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DefaultController extends AbstractController
{
    public function __construct(private RequestStack $requestStack)
    {
    }

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

    #[Route('/debug/session-test', name: 'debug_session_test', methods: ['GET'])]
    public function debugSessionTest(Request $request): Response
    {
        $session = $request->getSession();
        $session->set('test_key', 'test_value_'.time());
        $session->set('test_timestamp', time());

        return $this->json([
            'session_id' => $session->getId(),
            'session_started' => true,
            'test_key_set' => $session->get('test_key'),
            'timestamp' => $session->get('test_timestamp'),
            'all_keys' => array_keys($session->all()),
            'cookies' => $request->cookies->all(),
            'server_https' => $request->server->get('HTTPS'),
            'server_ssl' => $request->server->get('SSL_TLS_SNI'),
        ]);
    }

    public function debugAuth(Request $request): Response
    {
        $user = $this->getUser();
        $session = $request->getSession();

        // Check for security token
        $token = $this->tokenStorage?->getToken();

        $debug = [
            'user_from_getUser' => $user ? $user->getEmail() : null,
            'is_authenticated' => $this->isGranted('IS_AUTHENTICATED_FULLY'),
            'token_exists' => null !== $token,
            'token_class' => $token ? get_class($token) : null,
            'session_id' => $session->getId(),
            'session_keys' => array_keys($session->all()),
            'session_count' => count($session->all()),
            'session_attributes' => array_filter($session->all(), function ($key) {
                return !str_starts_with($key, '_sf2'); // Filter out Symfony internal session keys
            }, ARRAY_FILTER_USE_KEY),
            'server_vars' => [
                'REQUEST_METHOD' => $request->server->get('REQUEST_METHOD'),
                'HTTP_HOST' => $request->server->get('HTTP_HOST'),
                'REQUEST_URI' => $request->server->get('REQUEST_URI'),
                'REMOTE_ADDR' => $request->server->get('REMOTE_ADDR'),
            ],
            'has_cookies' => !empty($request->cookies->all()),
            'cookie_count' => count($request->cookies->all()),
            'environment' => $this->getParameter('kernel.environment'),
            'debug_mode' => $this->getParameter('kernel.debug'),
        ];

        return $this->render('debug/auth.html.twig', [
            'debug' => $debug,
        ]);
    }
}
