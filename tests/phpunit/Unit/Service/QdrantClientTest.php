<?php

declare(strict_types=1);

namespace App\Tests\phpunit\Unit\Service;

use App\Service\QdrantClient;
use PHPUnit\Framework\TestCase;

class QdrantClientTest extends TestCase
{
    private QdrantClient $client;

    protected function setUp(): void
    {
        // Set required environment variables
        putenv('QDRANT_HOST=localhost');
        putenv('QDRANT_PORT=6333');

        $this->client = new QdrantClient();
    }

    protected function tearDown(): void
    {
        putenv('QDRANT_HOST');
        putenv('QDRANT_PORT');
    }

    public function testConstructorUsesDefaultValues(): void
    {
        putenv('QDRANT_HOST');
        putenv('QDRANT_PORT');

        $client = new QdrantClient();

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

        $this->assertEquals('localhost', $hostProperty->getValue($this->client));
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
        putenv('QDRANT_HOST');
        putenv('QDRANT_PORT');

        $client = new QdrantClient();

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
