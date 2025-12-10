<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\OpenAIEmbeddingClientInterface;
use OpenAI;

/**
 * OpenAI client for generating embeddings using the openai-php/client library.
 */
final class OpenAIEmbeddingClient implements OpenAIEmbeddingClientInterface
{
    private OpenAI\Client $client;
    private string $model;

    public function __construct()
    {
        $apiKey = $this->getEnvVar('OPENAI_API_KEY');
        $this->model = $this->getEnvVar('OPENAI_MODEL', 'text-embedding-3-small');
        $this->client = \OpenAI::client($apiKey);
    }

    /**
     * Get environment variable value with validation.
     */
    private function getEnvVar(string $name, ?string $default = null): string
    {
        $value = getenv($name) ?: ($_ENV[$name] ?? $default);

        if (null === $value) {
            throw new \RuntimeException("Required environment variable '{$name}' is not set");
        }

        return $value;
    }

    public function getEmbedding(string $text): array
    {
        $embeddings = $this->getEmbeddingsBatch([$text]);

        return $embeddings[0];
    }

    public function getEmbeddingsBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        // Validate texts
        foreach ($texts as $text) {
            if (!is_string($text) || empty(trim($text))) {
                throw new \InvalidArgumentException('All texts must be non-empty strings');
            }
        }

        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $texts,
                'encoding_format' => 'float',
            ]);

            // Sort by index to maintain order
            $data = $response->embeddings;
            usort($data, fn ($a, $b) => $a->index <=> $b->index);

            $embeddings = [];
            foreach ($data as $item) {
                $embeddings[] = $item->embedding;
            }

            return $embeddings;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to generate embeddings: '.$e->getMessage(), 0, $e);
        }
    }
}
