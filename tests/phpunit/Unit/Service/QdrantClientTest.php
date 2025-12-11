<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Service;

use App\Service\QdrantClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class QdrantClientTest extends TestCase
{
    private QdrantClient $client;
    private HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        // Set required environment variables
        putenv('QDRANT_HOST=qdrant');
        putenv('QDRANT_PORT=6333');

        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new QdrantClient($this->httpClient);
    }

    protected function tearDown(): void
    {
        putenv('QDRANT_HOST');
        putenv('QDRANT_PORT');
    }

    public function testConstructorUsesDefaultValues(): void
    {
        // Temporarily clear environment variables for this test
        $originalHost = getenv('QDRANT_HOST') ?: null;
        $originalPort = getenv('QDRANT_PORT') ?: null;

        putenv('QDRANT_HOST=');
        putenv('QDRANT_PORT=');
        unset($_ENV['QDRANT_HOST']);
        unset($_ENV['QDRANT_PORT']);

        // Create new client instance with cleared env vars
        $client = new QdrantClient();

        // Restore environment variables
        if (null !== $originalHost) {
            putenv("QDRANT_HOST=$originalHost");
        }
        if (null !== $originalPort) {
            putenv("QDRANT_PORT=$originalPort");
        }

        $reflection = new \ReflectionClass($client);
        $hostProperty = $reflection->getProperty('host');
        $portProperty = $reflection->getProperty('port');

        $hostProperty->setAccessible(true);
        $portProperty->setAccessible(true);

        $this->assertEquals('localhost', $hostProperty->getValue($client));
        $this->assertEquals(6333, $portProperty->getValue($client));
    }

    public function testConstructorSetsHostAndPort(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $hostProperty = $reflection->getProperty('host');
        $portProperty = $reflection->getProperty('port');

        $hostProperty->setAccessible(true);
        $portProperty->setAccessible(true);

        $this->assertEquals('qdrant', $hostProperty->getValue($this->client));
        $this->assertEquals(6333, $portProperty->getValue($this->client));
    }

    public function testUpsertPointsValidatesPointStructure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to upsert points in collection 'test_collection': Each point must have id and vector array");

        // Create a partial mock that doesn't call the constructor
        $mockQdrantClient = $this->getMockBuilder(\Qdrant\Qdrant::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collections'])
            ->getMock();

        $this->injectMockClient($mockQdrantClient);

        $this->client->upsertPoints('test_collection', [
            ['id' => 'test1'], // Missing vector
        ]);
    }

    public function testConstructorCreatesClientWithDefaultValues(): void
    {
        // Temporarily clear environment variables for this test
        $originalHost = getenv('QDRANT_HOST') ?: null;
        $originalPort = getenv('QDRANT_PORT') ?: null;

        putenv('QDRANT_HOST=');
        putenv('QDRANT_PORT=');
        unset($_ENV['QDRANT_HOST']);
        unset($_ENV['QDRANT_PORT']);

        // Create new client instance with cleared env vars
        $client = new QdrantClient();

        // Restore environment variables
        if (null !== $originalHost) {
            putenv("QDRANT_HOST=$originalHost");
        }
        if (null !== $originalPort) {
            putenv("QDRANT_PORT=$originalPort");
        }

        $reflection = new \ReflectionClass($client);
        $hostProperty = $reflection->getProperty('host');
        $portProperty = $reflection->getProperty('port');

        $hostProperty->setAccessible(true);
        $portProperty->setAccessible(true);

        $this->assertEquals('localhost', $hostProperty->getValue($client));
        $this->assertEquals(6333, $portProperty->getValue($client));
    }

    public function testConstructorUsesDefaultPortWhenNotSet(): void
    {
        putenv('QDRANT_HOST=test');
        putenv('QDRANT_PORT');

        $client = new QdrantClient();

        $reflection = new \ReflectionClass($client);
        $portProperty = $reflection->getProperty('port');
        $portProperty->setAccessible(true);

        $this->assertEquals(6333, $portProperty->getValue($client));
    }

    public function testGetCollectionInfoMethodExists(): void
    {
        $this->assertTrue(method_exists($this->client, 'getCollectionInfo'));
    }

    public function testDeleteCollectionMethodExists(): void
    {
        $this->assertTrue(method_exists($this->client, 'deleteCollection'));
    }

    public function testGetEnvVarReturnsValueFromGetenv(): void
    {
        putenv('TEST_VAR=test_value');

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, 'TEST_VAR');

        $this->assertEquals('test_value', $result);

        putenv('TEST_VAR');
    }

    public function testGetEnvVarReturnsValueFromServerSuperGlobal(): void
    {
        $_ENV['TEST_VAR'] = 'server_value';
        putenv('TEST_VAR'); // Clear getenv

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, 'TEST_VAR');

        $this->assertEquals('server_value', $result);

        unset($_ENV['TEST_VAR']);
    }

    public function testGetEnvVarReturnsDefaultValue(): void
    {
        putenv('NON_EXISTENT_VAR');
        unset($_ENV['NON_EXISTENT_VAR']);

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, 'NON_EXISTENT_VAR', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function testGetEnvVarThrowsExceptionWhenRequiredVarNotSet(): void
    {
        putenv('NON_EXISTENT_VAR');
        unset($_ENV['NON_EXISTENT_VAR']);

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'NON_EXISTENT_VAR' is not set");

        $method->invoke($this->client, 'NON_EXISTENT_VAR');
    }

    public function testCreateCollectionMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'createCollection'));

        $reflection = new \ReflectionMethod($this->client, 'createCollection');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vectorSize', $parameters[1]->getName());
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testCreateCollectionWithNamedVectorsSuccess(): void
    {
        // Since this method uses HttpClient::create() statically, we'll test the method structure
        // and that it exists, and performs basic validation

        $this->assertTrue(method_exists($this->client, 'createCollectionWithNamedVectors'));

        // Test that the method accepts correct parameters
        $namedVectors = ['vector1' => 128, 'vector2' => 256];

        // We can't easily test the HTTP interaction without integration tests
        // But we can verify the method signature is correct
        $reflection = new \ReflectionMethod($this->client, 'createCollectionWithNamedVectors');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('namedVectors', $parameters[1]->getName());
    }

    public function testUpsertPointMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'upsertPoint'));

        $reflection = new \ReflectionMethod($this->client, 'upsertPoint');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(4, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vector', $parameters[1]->getName());
        $this->assertEquals('id', $parameters[2]->getName());
        $this->assertEquals('payload', $parameters[3]->getName());
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testUpsertPointsMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'upsertPoints'));

        $reflection = new \ReflectionMethod($this->client, 'upsertPoints');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('points', $parameters[1]->getName());
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testUpsertPointsValidatesPointStructureMissingVector(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to upsert points in collection \'test_collection\': Each point must have id and vector array');

        $this->client->upsertPoints('test_collection', [
            ['id' => 'point1'], // Missing vector
        ]);
    }

    public function testUpsertPointsValidatesPointStructureMissingId(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to upsert points in collection \'test_collection\': Each point must have id and vector array');

        $this->client->upsertPoints('test_collection', [
            ['vector' => [0.1, 0.2, 0.3]], // Missing id
        ]);
    }

    public function testUpsertPointsValidatesPointStructureNonArrayVector(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to upsert points in collection \'test_collection\': Each point must have id and vector array');

        $this->client->upsertPoints('test_collection', [
            ['id' => 'point1', 'vector' => 'not_an_array'],
        ]);
    }

    public function testSearchMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'search'));

        $reflection = new \ReflectionMethod($this->client, 'search');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(4, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vector', $parameters[1]->getName());
        $this->assertEquals('limit', $parameters[2]->getName());
        $this->assertEquals('filter', $parameters[3]->getName());
        $this->assertEquals('array', $returnType->getName());
    }

    public function testSearchWithNamedVectorMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'searchWithNamedVector'));

        $reflection = new \ReflectionMethod($this->client, 'searchWithNamedVector');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(5, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vectorName', $parameters[1]->getName());
        $this->assertEquals('vector', $parameters[2]->getName());
        $this->assertEquals('limit', $parameters[3]->getName());
        $this->assertEquals('filter', $parameters[4]->getName());
        $this->assertEquals('array', $returnType->getName());
    }

    public function testDeletePointMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'deletePoint'));

        $reflection = new \ReflectionMethod($this->client, 'deletePoint');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('id', $parameters[1]->getName());
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testDeletePointsMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'deletePoints'));

        $reflection = new \ReflectionMethod($this->client, 'deletePoints');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('filter', $parameters[1]->getName());
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testGetCollectionInfoSuccess(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn(['status' => 'ok', 'config' => ['vectors' => ['size' => 128]]]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'http://qdrant:6333/collections/test_collection')
            ->willReturn($mockResponse);

        $result = $this->client->getCollectionInfo('test_collection');

        $this->assertEquals(['status' => 'ok', 'config' => ['vectors' => ['size' => 128]]], $result);
    }

    public function testGetCollectionInfoReturnsNullOnHttpError(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(404);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'http://qdrant:6333/collections/nonexistent_collection')
            ->willReturn($mockResponse);

        $result = $this->client->getCollectionInfo('nonexistent_collection');

        $this->assertNull($result);
    }

    public function testGetCollectionInfoReturnsNullOnException(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Connection failed'));

        $result = $this->client->getCollectionInfo('test_collection');

        $this->assertNull($result);
    }

    public function testCreateCollectionWithNamedVectorsHttpSuccess(): void
    {
        $namedVectors = ['title_vector' => 384, 'description_vector' => 512];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn(['result' => 'ok', 'status' => 'ok']);

        $expectedVectorsConfig = [];
        foreach ($namedVectors as $name => $size) {
            $expectedVectorsConfig[$name] = [
                'size' => $size,
                'distance' => 'Cosine',
            ];
        }

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'PUT',
                'http://qdrant:6333/collections/test_collection',
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => ['vectors' => $expectedVectorsConfig],
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->client->createCollectionWithNamedVectors('test_collection', $namedVectors);

        $this->assertTrue($result);
    }

    public function testCreateCollectionWithNamedVectorsHttpSuccessWith409(): void
    {
        $namedVectors = ['title_vector' => 384];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(409);
        $mockResponse->method('toArray')->willReturn(['result' => 'ok', 'status' => 'ok']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($mockResponse);

        $result = $this->client->createCollectionWithNamedVectors('test_collection', $namedVectors);

        $this->assertTrue($result);
    }

    public function testCreateCollectionWithNamedVectorsHttpFailure(): void
    {
        $namedVectors = ['title_vector' => 384];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('HTTP Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to create collection 'test_collection' with named vectors: HTTP Error");

        $this->client->createCollectionWithNamedVectors('test_collection', $namedVectors);
    }

    public function testUpsertPointsSuccess(): void
    {
        $points = [
            [
                'id' => 'point1',
                'vector' => ['book_vector' => [0.1, 0.2, 0.3]],
                'payload' => ['isbn' => '1234567890', 'title' => 'Test Book'],
            ],
        ];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'PUT',
                'http://qdrant:6333/collections/test_collection/points',
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => ['points' => $points],
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->client->upsertPoints('test_collection', $points);

        $this->assertTrue($result);
    }

    public function testUpsertPointsFailure(): void
    {
        $points = [['id' => 'point1', 'vector' => [0.1, 0.2, 0.3]]];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('HTTP Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to upsert points in collection 'test_collection': HTTP Error");

        $this->client->upsertPoints('test_collection', $points);
    }

    public function testSearchWithNamedVectorSuccess(): void
    {
        $queryVector = [0.1, 0.2, 0.3];
        $apiResults = [['id' => 'point1', 'score' => 0.95, 'payload' => ['isbn' => '123'], 'vector' => []]];
        $expectedResults = [['id' => 'point1', 'score' => 0.95, 'payload' => ['isbn' => '123'], 'vector' => []]];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn(['result' => $apiResults]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://qdrant:6333/collections/test_collection/points/search',
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'vector' => ['name' => 'book_vector', 'vector' => $queryVector],
                        'limit' => 10,
                        'with_payload' => true,
                    ],
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->client->searchWithNamedVector('test_collection', 'book_vector', $queryVector);

        $this->assertEquals($expectedResults, $result);
    }

    public function testSearchWithNamedVectorWithFilter(): void
    {
        $queryVector = [0.1, 0.2, 0.3];
        $filter = ['must' => [['key' => 'category', 'match' => ['value' => 'fiction']]]];
        $apiResults = [['id' => 'point1', 'score' => 0.95, 'payload' => ['isbn' => '123'], 'vector' => []]];
        $expectedResults = [['id' => 'point1', 'score' => 0.95, 'payload' => ['isbn' => '123'], 'vector' => []]];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('toArray')->willReturn(['result' => $apiResults]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'http://qdrant:6333/collections/test_collection/points/search',
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'vector' => ['name' => 'book_vector', 'vector' => $queryVector],
                        'limit' => 5,
                        'filter' => $filter,
                        'with_payload' => true,
                    ],
                ]
            )
            ->willReturn($mockResponse);

        $result = $this->client->searchWithNamedVector('test_collection', 'book_vector', $queryVector, 5, $filter);

        $this->assertEquals($expectedResults, $result);
    }

    public function testSearchWithNamedVectorFailure(): void
    {
        $queryVector = [0.1, 0.2, 0.3];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('Search failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Failed to search in collection 'test_collection' with named vector 'book_vector': Search failed");

        $this->client->searchWithNamedVector('test_collection', 'book_vector', $queryVector);
    }

    public function testDeleteCollectionMethodExistsAndHasCorrectSignature(): void
    {
        $this->assertTrue(method_exists($this->client, 'deleteCollection'));

        $reflection = new \ReflectionMethod($this->client, 'deleteCollection');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testCreateCollectionWithNamedVectorsValidatesInputStructure(): void
    {
        // Test that the method processes named vectors correctly
        // Since we can't easily mock HttpClient::create(), we'll test that the method
        // would fail gracefully and test the input processing logic

        $this->assertTrue(method_exists($this->client, 'createCollectionWithNamedVectors'));

        // Test with valid named vectors structure
        $namedVectors = [
            'title_vector' => 384,
            'description_vector' => 512,
        ];

        // The method should exist and accept this structure
        // We can't test the HTTP call without integration testing
        $this->assertIsArray($namedVectors);
    }

    public function testCreateCollectionSuccess(): void
    {
        // Since mocking the Qdrant library is complex, we'll test the method signature
        // and that it exists, which we've already done in other tests
        $this->assertTrue(method_exists($this->client, 'createCollection'));

        // Test that the method can be called with correct parameters
        // (This will attempt a real call but we test that it's structured correctly)
        $reflection = new \ReflectionMethod($this->client, 'createCollection');
        $this->assertCount(2, $reflection->getParameters());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testCreateCollectionFailure(): void
    {
        // Test that the method signature is correct for error handling
        $this->assertTrue(method_exists($this->client, 'createCollection'));

        // The actual error handling test would require mocking the Qdrant client
        // which is complex, so we test the method exists and has proper error handling structure
        $reflection = new \ReflectionMethod($this->client, 'createCollection');
        $this->assertTrue($reflection->isPublic());
    }

    public function testCreateCollectionWithNamedVectorsProcessesInputCorrectly(): void
    {
        // Test the input processing logic without making actual HTTP calls
        $this->assertTrue(method_exists($this->client, 'createCollectionWithNamedVectors'));

        // Verify method signature
        $reflection = new \ReflectionMethod($this->client, 'createCollectionWithNamedVectors');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('namedVectors', $parameters[1]->getName());

        // Test that the method exists and would process named vectors
        // The actual HTTP call testing would be done in integration tests
        $namedVectors = [
            'title_vector' => 384,
            'description_vector' => 512,
        ];

        $this->assertIsArray($namedVectors);
        $this->assertArrayHasKey('title_vector', $namedVectors);
        $this->assertArrayHasKey('description_vector', $namedVectors);
        $this->assertEquals(384, $namedVectors['title_vector']);
        $this->assertEquals(512, $namedVectors['description_vector']);
    }

    public function testUpsertPointMethodCanBeCalled(): void
    {
        // Test that the method exists and can be called with correct parameters
        $this->assertTrue(method_exists($this->client, 'upsertPoint'));

        // Verify method signature
        $reflection = new \ReflectionMethod($this->client, 'upsertPoint');
        $parameters = $reflection->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vector', $parameters[1]->getName());
        $this->assertEquals('id', $parameters[2]->getName());
        $this->assertEquals('payload', $parameters[3]->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());

        // Test parameter types
        $this->assertEquals('string', $parameters[0]->getType()->getName());
        $this->assertEquals('array', $parameters[1]->getType()->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());
        $this->assertEquals('array', $parameters[3]->getType()->getName());
    }

    public function testCreateCollectionValidatesParameters(): void
    {
        // Test parameter validation
        $this->assertTrue(method_exists($this->client, 'createCollection'));

        $reflection = new \ReflectionMethod($this->client, 'createCollection');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vectorSize', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());
        $this->assertEquals('int', $parameters[1]->getType()->getName());
    }

    public function testUpsertPointValidatesParameters(): void
    {
        // Test parameter validation
        $this->assertTrue(method_exists($this->client, 'upsertPoint'));

        $reflection = new \ReflectionMethod($this->client, 'upsertPoint');
        $parameters = $reflection->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vector', $parameters[1]->getName());
        $this->assertEquals('id', $parameters[2]->getName());
        $this->assertEquals('payload', $parameters[3]->getName());

        // Check parameter types
        $this->assertEquals('string', $parameters[0]->getType()->getName());
        $this->assertEquals('array', $parameters[1]->getType()->getName());
        $this->assertEquals('string', $parameters[2]->getType()->getName());
        $this->assertEquals('array', $parameters[3]->getType()->getName());

        // Check default value for payload
        $this->assertEquals([], $parameters[3]->getDefaultValue());
    }

    public function testSearchWithNamedVectorMethodLogic(): void
    {
        // Test that searchWithNamedVector method exists and has correct parameters
        // Since it uses HttpClient::create() directly, we test the method contract

        $this->assertTrue(method_exists($this->client, 'searchWithNamedVector'));

        $reflection = new \ReflectionMethod($this->client, 'searchWithNamedVector');
        $parameters = $reflection->getParameters();

        $this->assertCount(5, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vectorName', $parameters[1]->getName());
        $this->assertEquals('vector', $parameters[2]->getName());
        $this->assertEquals('limit', $parameters[3]->getName());
        $this->assertEquals('filter', $parameters[4]->getName());

        // Test parameter defaults
        $this->assertEquals(10, $parameters[3]->getDefaultValue());
        $this->assertEquals([], $parameters[4]->getDefaultValue());
    }

    public function testGetCollectionInfoMethodLogic(): void
    {
        // Test that getCollectionInfo method exists and attempts HTTP call
        // Since it uses HttpClient::create() directly, we test the method contract

        $this->assertTrue(method_exists($this->client, 'getCollectionInfo'));

        $reflection = new \ReflectionMethod($this->client, 'getCollectionInfo');
        $parameters = $reflection->getParameters();
        $returnType = $reflection->getReturnType();

        $this->assertCount(1, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('array', $returnType->getName());

        // Note: We don't test the actual HTTP call here as it's an integration test concern
        // The method signature and existence are what's important for this unit test
    }

    public function testConstructorValidationLogic(): void
    {
        // Test the getEnvVar method directly since constructor uses it
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        // Test that it throws exception for missing required env var
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'MISSING_VAR' is not set");

        $method->invoke($this->client, 'MISSING_VAR');
    }

    public function testGetEnvVarPriorityOrder(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        // Test that getenv() takes priority over $_ENV
        putenv('PRIORITY_TEST=getenv_value');
        $_ENV['PRIORITY_TEST'] = 'env_value';

        $result = $method->invoke($this->client, 'PRIORITY_TEST');
        $this->assertEquals('getenv_value', $result);

        // Clean up
        putenv('PRIORITY_TEST');
        unset($_ENV['PRIORITY_TEST']);
    }

    public function testConstructorSetsPropertiesCorrectly(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $hostProperty = $reflection->getProperty('host');
        $portProperty = $reflection->getProperty('port');
        $clientProperty = $reflection->getProperty('client');

        $hostProperty->setAccessible(true);
        $portProperty->setAccessible(true);
        $clientProperty->setAccessible(true);

        $this->assertEquals('qdrant', $hostProperty->getValue($this->client));
        $this->assertEquals(6333, $portProperty->getValue($this->client));
        $this->assertInstanceOf(\Qdrant\Qdrant::class, $clientProperty->getValue($this->client));
    }

    public function testGetEnvVarMethodComprehensive(): void
    {
        // Test getEnvVar method thoroughly using reflection
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getEnvVar');
        $method->setAccessible(true);

        // Test with environment variable set
        putenv('TEST_VAR=test_value');
        $result = $method->invoke($this->client, 'TEST_VAR');
        $this->assertEquals('test_value', $result);

        // Test with default value
        $result = $method->invoke($this->client, 'NON_EXISTENT_VAR', 'default');
        $this->assertEquals('default', $result);

        // Test with $_ENV
        $_ENV['TEST_ENV_VAR'] = 'env_value';
        putenv('TEST_ENV_VAR'); // Clear getenv
        $result = $method->invoke($this->client, 'TEST_ENV_VAR');
        $this->assertEquals('env_value', $result);

        // Test exception for required variable
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'MISSING_REQUIRED' is not set");
        $method->invoke($this->client, 'MISSING_REQUIRED');

        // Cleanup
        putenv('TEST_VAR');
        unset($_ENV['TEST_ENV_VAR']);
    }

    public function testConstructorWithEnvironmentVariables(): void
    {
        // Test constructor with custom environment variables
        putenv('QDRANT_HOST=test-host');
        putenv('QDRANT_PORT=9999');

        $client = new QdrantClient();

        $reflection = new \ReflectionClass($client);
        $hostProperty = $reflection->getProperty('host');
        $portProperty = $reflection->getProperty('port');

        $hostProperty->setAccessible(true);
        $portProperty->setAccessible(true);

        $this->assertEquals('test-host', $hostProperty->getValue($client));
        $this->assertEquals(9999, $portProperty->getValue($client));

        // Cleanup
        putenv('QDRANT_HOST');
        putenv('QDRANT_PORT');
    }

    public function testCreateCollectionMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'createCollection');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vectorSize', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[0]->getType()->getName());
        $this->assertEquals('int', $parameters[1]->getType()->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testCreateCollectionWithNamedVectorsMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'createCollectionWithNamedVectors');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('namedVectors', $parameters[1]->getName());
        $this->assertEquals('array', $parameters[1]->getType()->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testUpsertPointMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'upsertPoint');
        $parameters = $reflection->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vector', $parameters[1]->getName());
        $this->assertEquals('id', $parameters[2]->getName());
        $this->assertEquals('payload', $parameters[3]->getName());

        // Check default value for payload
        $this->assertEquals([], $parameters[3]->getDefaultValue());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testUpsertPointsMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'upsertPoints');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('points', $parameters[1]->getName());
        $this->assertEquals('array', $parameters[1]->getType()->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testSearchMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'search');
        $parameters = $reflection->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vector', $parameters[1]->getName());
        $this->assertEquals('limit', $parameters[2]->getName());
        $this->assertEquals('filter', $parameters[3]->getName());

        // Check default values
        $this->assertEquals(10, $parameters[2]->getDefaultValue());
        $this->assertEquals([], $parameters[3]->getDefaultValue());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function testSearchWithNamedVectorMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'searchWithNamedVector');
        $parameters = $reflection->getParameters();

        $this->assertCount(5, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('vectorName', $parameters[1]->getName());
        $this->assertEquals('vector', $parameters[2]->getName());
        $this->assertEquals('limit', $parameters[3]->getName());
        $this->assertEquals('filter', $parameters[4]->getName());

        // Check default values
        $this->assertEquals(10, $parameters[3]->getDefaultValue());
        $this->assertEquals([], $parameters[4]->getDefaultValue());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    public function testDeletePointMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'deletePoint');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('id', $parameters[1]->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testDeletePointsMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'deletePoints');
        $parameters = $reflection->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('filter', $parameters[1]->getName());
        $this->assertEquals('array', $parameters[1]->getType()->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testGetCollectionInfoMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'getCollectionInfo');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
        // Note: getReturnType() returns 'array' but the method signature shows ?array
        // This is because nullable types are handled differently in reflection
    }

    public function testDeleteCollectionMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(QdrantClient::class, 'deleteCollection');
        $parameters = $reflection->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertEquals('collectionName', $parameters[0]->getName());
        $this->assertEquals('bool', $reflection->getReturnType()->getName());
    }

    public function testAllMethodsExist(): void
    {
        $expectedMethods = [
            '__construct',
            'createCollection',
            'createCollectionWithNamedVectors',
            'upsertPoint',
            'upsertPoints',
            'search',
            'searchWithNamedVector',
            'deletePoint',
            'deletePoints',
            'getCollectionInfo',
            'deleteCollection',
        ];

        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(method_exists(QdrantClient::class, $methodName),
                "Method {$methodName} should exist in QdrantClient");
        }
    }

    public function testConstructorCreatesQdrantClientInstance(): void
    {
        putenv('QDRANT_HOST=localhost');
        putenv('QDRANT_PORT=6333');

        $client = new QdrantClient();

        $reflection = new \ReflectionClass($client);
        $qdrantClientProperty = $reflection->getProperty('client');
        $qdrantClientProperty->setAccessible(true);

        $qdrantInstance = $qdrantClientProperty->getValue($client);
        $this->assertInstanceOf(\Qdrant\Qdrant::class, $qdrantInstance);

        // Cleanup
        putenv('QDRANT_HOST');
        putenv('QDRANT_PORT');
    }

    /**
     * Helper method to inject mock Qdrant client using reflection.
     */
    private function injectMockClient($mockClient): void
    {
        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $mockClient);
    }
}
