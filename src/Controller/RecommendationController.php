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
        private RecommendationServiceInterface $recommendationService,
    ) {
    }

    /**
     * Handle recommendation form submission via HTMX.
     */
    public function create(Request $request): Response
    {
        $description = $request->request->get('description');
        $tagNames = $request->request->all('tags', []);

        // Walidacja danych
        if (empty($description) || strlen($description) < 30 || strlen($description) > 500) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'components.recommendations.error.description_length',
            ], new Response('', 400));
        }

        if (count($tagNames) < 2) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'components.recommendations.error.min_tags',
            ], new Response('', 400));
        }

        try {
            // Find tags by names
            $tags = [];
            foreach ($tagNames as $tagName) {
                $tag = $this->recommendationService->findTagByName($tagName);
                if ($tag) {
                    $tags[] = $tag;
                }
            }

            if (count($tags) < 2) {
                return $this->render('components/recommendation_error.html.twig', [
                    'error' => 'components.recommendations.error.invalid_tags',
                ], new Response('', 400));
            }

            // Create recommendation
            $recommendation = $this->recommendationService->createOrUpdateRecommendation(
                $this->getUser()->getId(),
                $description,
                array_map(fn ($tag) => $tag->getId(), $tags)
            );

            // Search for similar books and store results
            try {
                $this->recommendationService->searchAndStoreSimilarEbooks($recommendation);
            } catch (\Exception $e) {
                // Log error but don't fail the recommendation creation
                // Search can be retried later if needed
            }

            // Return success - HTMX can handle this
            return $this->render('components/recommendation_success.html.twig', [
                'recommendation' => $recommendation,
            ]);
        } catch (\Exception $e) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'components.recommendations.error.generic',
            ], new Response('', 500));
        }
    }

    /**
     * Show user's recommendations (for HTMX loading).
     */
    public function list(): Response
    {
        // TODO: Implement listing recommendations
        // For now return placeholder
        return $this->render('components/recommendations_list.html.twig', [
            'recommendations' => [],
        ]);
    }
}
