<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RecommendationServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class RecommendationController extends AbstractController
{
    public function __construct(
        private RecommendationServiceInterface $recommendationService,
        private EntityManagerInterface $entityManager,
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
        $userId = $this->getUser()->getId();

        $recommendations = $this->entityManager
            ->getRepository(\App\Entity\Recommendation::class)
            ->findBy(
                ['user' => $userId],
                ['updatedAt' => 'DESC']
            );

        return $this->render('components/recommendations_list.html.twig', [
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Show recommendation details with found books (for modal).
     */
    public function show(int $id): Response
    {
        $userId = $this->getUser()->getId();

        // Znajdź rekomendację użytkownika
        $recommendation = $this->entityManager
            ->getRepository(\App\Entity\Recommendation::class)
            ->findOneBy(['id' => $id, 'user' => $userId]);

        if (!$recommendation) {
            throw $this->createNotFoundException('Recommendation not found');
        }

        // Pobierz znalezione książki posortowane wg podobieństwa
        $books = $this->entityManager
            ->getRepository(\App\Entity\RecommendationResult::class)
            ->findBy(
                ['recommendation' => $recommendation],
                ['similarityScore' => 'DESC']
            );

        return $this->render('components/recommendation_details.html.twig', [
            'recommendation' => $recommendation,
            'books' => $books,
        ]);
    }

    /**
     * Delete user's recommendation.
     */
    public function delete(int $id): Response
    {
        $userId = $this->getUser()->getId();

        // Znajdź rekomendację użytkownika
        $recommendation = $this->entityManager
            ->getRepository(\App\Entity\Recommendation::class)
            ->findOneBy(['id' => $id, 'user' => $userId]);

        if (!$recommendation) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'dashboard.recommendation_not_found',
            ], new Response('', 404));
        }

        try {
            // Usuń rekomendację (cascade delete usunie też wyniki)
            $this->entityManager->remove($recommendation);
            $this->entityManager->flush();

            return $this->render('components/recommendation_success.html.twig', [
                'message' => 'dashboard.recommendation_deleted',
                'type' => 'delete',
            ]);
        } catch (\Exception $e) {
            return $this->render('components/recommendation_error.html.twig', [
                'error' => 'dashboard.delete_failed',
            ], new Response('', 500));
        }
    }
}
