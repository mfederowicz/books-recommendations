<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RecommendationServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class RecommendationController extends AbstractController
{
    public function __construct(
        private RecommendationServiceInterface $recommendationService
    ) {}

    /**
     * Handle recommendation form submission via HTMX
     */
    public function create(Request $request): Response
    {
        $description = $request->request->get('description');
        $tagNames = $request->request->all('tags', []);

        // Walidacja danych
        if (empty($description) || strlen($description) < 30 || strlen($description) > 500) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'Description must be between 30 and 500 characters.'
            ], new Response('', 400));
        }

        if (count($tagNames) < 2) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'Please select at least 2 tags.'
            ], new Response('', 400));
        }

        try {
            // Znajdź tagi po nazwach
            $tags = [];
            foreach ($tagNames as $tagName) {
                $tag = $this->recommendationService->findTagByName($tagName);
                if ($tag) {
                    $tags[] = $tag;
                }
            }

            if (count($tags) < 2) {
                return $this->render('components/recommendation_error.html.twig', [
                    'error' => 'Some selected tags are invalid. Please try again.'
                ], new Response('', 400));
            }

            // Utwórz rekomendację
            $recommendation = $this->recommendationService->createOrUpdateRecommendation(
                $this->getUser()->getId(),
                $description,
                array_map(fn($tag) => $tag->getId(), $tags)
            );

            // Zwróć sukces - HTMX może to obsłużyć
            return $this->render('components/recommendation_success.html.twig', [
                'recommendation' => $recommendation
            ]);

        } catch (\Exception $e) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'An error occurred while processing your recommendation. Please try again.'
            ], new Response('', 500));
        }
    }

    /**
     * Show user's recommendations (for HTMX loading)
     */
    public function list(): Response
    {
        // TODO: Implement listing recommendations
        // For now return placeholder
        return $this->render('components/recommendations_list.html.twig', [
            'recommendations' => []
        ]);
    }
}
