<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index(): Response
    {
        // Tymczasowo zawsze pokazuj dashboard dla celów testowych
        return $this->render('dashboard.html.twig');

        if ($this->getUser()) {
            // Zalogowany użytkownik - pokaż dashboard
            return $this->render('dashboard.html.twig');
        }

        // Niezalogowany - pokaż landing page
        return $this->render('homepage.html.twig');
    }

    /**
     * Handle .well-known requests (Chrome DevTools, etc.).
     */
    public function wellKnown(): Response
    {
        return new Response('', 404);
    }
}
