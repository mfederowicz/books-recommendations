<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\QdrantClientInterface;
use Qdrant\Config;
use Qdrant\Http\Builder;
use Qdrant\Qdrant;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Qdrant vector database client implementation using hkulekci/qdrant library.
 */
final class QdrantClient implements QdrantClientInterface
{
    private Qdrant $client;
    private string $host;
    private int $port;
    private HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->host = $this->getEnvVar('QDRANT_HOST', 'localhost');
        $this->port = (int) $this->getEnvVar('QDRANT_PORT', '6333');
        $this->httpClient = $httpClient ?? \Symfony\Component\HttpClient\HttpClient::create();

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

    public function createCollectionWithNamedVectors(string $collectionName, array $namedVectors): bool
    {
        try {
            // For named vectors, we need to structure the config differently
            $vectorsConfig = [];
            foreach ($namedVectors as $name => $size) {
                $vectorsConfig[$name] = [
                    'size' => $size,
                    'distance' => 'Cosine',
                ];
            }

            // Use raw HTTP request to create collection with named vectors
            $httpClient = $this->httpClient;

            $response = $httpClient->request('PUT', "http://{$this->host}:{$this->port}/collections/{$collectionName}", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'vectors' => $vectorsConfig,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            // Qdrant returns 200 on success, 409 if collection already exists
            return (200 === $statusCode || 201 === $statusCode)
                   || (409 === $statusCode && isset($content['result']) && 'ok' === $content['status']);
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to create collection '{$collectionName}' with named vectors: ".$e->getMessage(), 0, $e);
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

            // Use HTTP client for upsert to handle named vectors properly
            $httpClient = $this->httpClient;

            $response = $httpClient->request('PUT', "http://{$this->host}:{$this->port}/collections/{$collectionName}/points", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'points' => $points,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            return 200 === $statusCode || 201 === $statusCode;
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

    public function searchWithNamedVector(string $collectionName, string $vectorName, array $vector, int $limit = 10, array $filter = []): array
    {
        try {
            // Use HTTP client for named vector search
            $searchParams = [
                'vector' => [
                    'name' => $vectorName,
                    'vector' => $vector,
                ],
                'limit' => $limit,
                'with_payload' => true,
            ];

            if (!empty($filter)) {
                $searchParams['filter'] = $filter;
            }

            $httpClient = $this->httpClient;
            $response = $httpClient->request('POST', "http://{$this->host}:{$this->port}/collections/{$collectionName}/points/search", [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => $searchParams,
            ]);

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                $errorContent = $response->getContent(false);
                error_log("Qdrant search failed: HTTP {$statusCode}, Response: {$errorContent}");

                return [];
            }

            $data = $response->toArray();
            $results = [];

            foreach ($data['result'] ?? [] as $result) {
                $results[] = [
                    'id' => $result['id'] ?? null,
                    'score' => $result['score'] ?? 0,
                    'payload' => $result['payload'] ?? [],
                    'vector' => $result['vector'] ?? [],
                ];
            }

            return $results;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to search in collection '{$collectionName}' with named vector '{$vectorName}': ".$e->getMessage(), 0, $e);
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
            // Use HTTP client for consistency
            $httpClient = $this->httpClient;

            $response = $httpClient->request('GET', "http://{$this->host}:{$this->port}/collections/{$collectionName}");

            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                return null;
            }

            $data = $response->toArray();

            return $data;
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
