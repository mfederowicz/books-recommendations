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
        $user = $this->getUser();

        // Debug logging for production troubleshooting
        if ($user) {
            error_log("User authenticated: " . $user->getEmail() . " - showing dashboard");
            // Logged in user - show dashboard
            return $this->render('dashboard.html.twig');
        } else {
            error_log("No authenticated user - showing homepage");
            // Not logged in - show landing page
            return $this->render('homepage.html.twig');
        }
    }

    /**
     * Handle .well-known requests (Chrome DevTools, etc.).
     */
    public function wellKnown(): Response
    {
        return new Response('', 404);
    }

    public function debugAuth(Request $request): Response
    {
        $user = $this->getUser();
        $session = $request->getSession();

        // Check for security token
        $token = null;
        try {
            $tokenStorage = $this->container->get('security.token_storage');
            $token = $tokenStorage->getToken();
        } catch (\Exception $e) {
            // Ignore if token storage is not available
        }

        $debug = [
            'user_from_getUser' => $user ? $user->getEmail() : null,
            'is_authenticated' => $this->isGranted('IS_AUTHENTICATED_FULLY'),
            'token_exists' => $token !== null,
            'token_class' => $token ? get_class($token) : null,
            'session_id' => $session->getId(),
            'session_keys' => array_keys($session->all()),
            'session_count' => count($session->all()),
            'session_attributes' => array_filter($session->all(), function($key) {
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
