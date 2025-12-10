<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\QdrantClientInterface;
use Qdrant\Config;
use Qdrant\Http\Builder;
use Qdrant\Qdrant;

/**
 * Qdrant vector database client implementation using hkulekci/qdrant library.
 */
final class QdrantClient implements QdrantClientInterface
{
    private Qdrant $client;
    private string $host;
    private int $port;

    public function __construct()
    {
        $this->host = $this->getEnvVar('QDRANT_HOST', 'localhost');
        $this->port = (int) $this->getEnvVar('QDRANT_PORT', '6333');

        $config = new Config($this->host, $this->port);
        $builder = new Builder();
        $transport = $builder->build($config);
        $this->client = new Qdrant($transport);
    }

    /**
     * Get environment variable with optional default value.
     */
    private function getEnvVar(string $name, ?string $default = null): string
    {
        $value = getenv($name) ?: ($_ENV[$name] ?? $default);

        if (null === $value) {
            throw new \RuntimeException("Required environment variable '{$name}' is not set");
        }

        return $value;
    }

    public function createCollection(string $collectionName, int $vectorSize): bool
    {
        try {
            $response = $this->client->collections($collectionName)->create([
                'vectors' => [
                    'size' => $vectorSize,
                    'distance' => 'Cosine',
                ],
            ]);

            return $response->isSuccess();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }

    public function upsertPoint(string $collectionName, array $vector, string $id, array $payload = []): bool
    {
        try {
            $point = [
                'id' => $id,
                'vector' => $vector,
                'payload' => $payload,
            ];

            $response = $this->client->collections($collectionName)->points()->upsert([$point]);

            return $response->isSuccess();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to upsert point '{$id}' in collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }

    public function upsertPoints(string $collectionName, array $points): bool
    {
        try {
            // Validate points structure
            foreach ($points as $point) {
                if (!isset($point['id']) || !isset($point['vector']) || !is_array($point['vector'])) {
                    throw new \InvalidArgumentException('Each point must have id and vector array');
                }
            }

            $response = $this->client->collections($collectionName)->points()->upsert($points);

            return $response->isSuccess();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to upsert points in collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }

    public function search(string $collectionName, array $vector, int $limit = 10, array $filter = []): array
    {
        try {
            $searchParams = [
                'vector' => $vector,
                'limit' => $limit,
            ];

            if (!empty($filter)) {
                $searchParams['filter'] = $filter;
            }

            $response = $this->client->collections($collectionName)->points()->search($searchParams);

            if (!$response->isSuccess()) {
                return [];
            }

            $results = [];
            foreach ($response->getData()['result'] ?? [] as $result) {
                $results[] = [
                    'id' => $result['id'] ?? null,
                    'score' => $result['score'] ?? 0,
                    'payload' => $result['payload'] ?? [],
                    'vector' => $result['vector'] ?? [],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to search in collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }

    public function deletePoint(string $collectionName, string $id): bool
    {
        try {
            $response = $this->client->collections($collectionName)->points()->delete([
                'points' => [$id],
            ]);

            return $response->isSuccess();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to delete point '{$id}' from collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }

    public function deletePoints(string $collectionName, array $filter): bool
    {
        try {
            $response = $this->client->collections($collectionName)->points()->delete([
                'filter' => $filter,
            ]);

            return $response->isSuccess();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to delete points from collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }

    public function getCollectionInfo(string $collectionName): ?array
    {
        try {
            $response = $this->client->collections($collectionName)->info();

            if (!$response->isSuccess()) {
                return null;
            }

            return $response->getData();
        } catch (\Exception $e) {
            return null; // Collection doesn't exist or error occurred
        }
    }

    public function deleteCollection(string $collectionName): bool
    {
        try {
            $response = $this->client->collections($collectionName)->delete();

            return $response->isSuccess();
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to delete collection '{$collectionName}': ".$e->getMessage(), 0, $e);
        }
    }
}
