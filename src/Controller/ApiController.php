<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ApiController extends AbstractController
{
    public function __construct(
        private TagService $tagService
    ) {}

    /**
     * Get tags for autocomplete functionality
     * Returns active tags starting with the given query (minimum 2 characters)
     */
    #[Route('/api/tags', name: 'api_tags_search', methods: ['GET'])]
    public function searchTags(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        // Validate query length
        if (strlen(trim($query)) < 2) {
            return new JsonResponse([
                'tags' => [],
                'total' => 0
            ]);
        }

        $tags = $this->tagService->findActiveTagsForAutocomplete($query);

        // Format response
        $formattedTags = array_map(function ($tag) {
            return [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'ascii' => $tag->getAscii()
            ];
        }, $tags);

        return new JsonResponse([
            'tags' => $formattedTags,
            'total' => count($formattedTags)
        ]);
    }
}
