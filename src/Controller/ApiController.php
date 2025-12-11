<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RecommendationServiceInterface;
use App\Service\TagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ApiController extends AbstractController
{
    public function __construct(
        private TagService $tagService,
        private RecommendationServiceInterface $recommendationService,
    ) {
    }

    /**
     * Get tags for autocomplete functionality
     * Returns active tags starting with the given query (minimum 2 characters).
     */
    #[Route('/api/tags', name: 'api_tags_search', methods: ['GET'])]
    public function searchTags(Request $request)
    {
        $query = $request->query->get('q', '');

        // Validate query length
        if (strlen(trim($query)) < 2) {
            return $this->renderTagSuggestions([]);
        }

        $tags = $this->tagService->findActiveTagsForAutocomplete($query);

        return $this->renderTagSuggestions($tags);
    }

    /**
     * Render tag suggestions as HTML fragment for HTMX.
     */
    private function renderTagSuggestions(array $tags)
    {
        return $this->render('components/tag_suggestions.html.twig', [
            'tags' => $tags,
        ]);
    }

    /**
     * Test endpoint for Qdrant search (temporary for testing).
     */
    public function testQdrantSearch(Request $request)
    {
        $text = $request->query->get('text', 'fantasy adventure with dragons and magic');
        $limit = (int) $request->query->get('limit', 5);

        try {
            $results = $this->recommendationService->findSimilarEbooks($text, $limit);

            return $this->json([
                'query' => $text,
                'limit' => $limit,
                'results' => array_map(function ($result) {
                    return [
                        'title' => $result['ebook']->getTitle(),
                        'author' => $result['ebook']->getAuthor(),
                        'isbn' => $result['ebook']->getIsbn(),
                        'tags' => $result['ebook']->getTags(),
                        'similarity_score' => round($result['similarity_score'], 4),
                    ];
                }, $results),
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'query' => $text,
            ], 500);
        }
    }
}
